<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\Source;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\Entry;
use Tests\TestCase;

class EditorialContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_sources_include_editorial_notes_and_citation_templates(): void
    {
        $source = Source::where('code', 'wfp')->firstOrFail();

        $this->assertStringContainsString('World Food Programme', $source->citation_template);
        $this->assertSame(
            'Rainfall indicators should be explained with their aggregation window, anomaly basis, geography level, and data status before interpretation.',
            $source->metadata['editorial_note']
        );

        $entry = Entry::query()
            ->where('collection', 'sources')
            ->where('slug', 'wfp')
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('aggregation window', $entry->get('notes'));
    }

    public function test_dataset_settings_include_methodology_caveats_and_glossary_labels(): void
    {
        $dataset = Dataset::where('code', 'nigeria_rainfall_subnational')->firstOrFail();

        $this->assertSame('subnational_rainfall_indicators', $dataset->settings['methodology_code']);
        $this->assertContains(
            'Missing or unavailable geography-period combinations must be shown as missing, not as zero rainfall.',
            $dataset->settings['caveats']
        );
        $this->assertSame(
            'Rainfall anomaly for the selected three-month period.',
            $dataset->settings['glossary']['r3q']
        );

        $entry = Entry::query()
            ->where('collection', 'datasets')
            ->where('slug', 'nigeria-rainfall-subnational')
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Glossary labels:', $entry->get('notes'));
        $this->assertStringContainsString('`rfh`', $entry->get('notes'));
    }

    public function test_methodology_entries_are_dataset_specific_and_related_to_datasets(): void
    {
        $methodology = Entry::query()
            ->where('collection', 'methodologies')
            ->where('slug', 'subnational-rainfall-indicators')
            ->first();

        $dataset = Entry::query()
            ->where('collection', 'datasets')
            ->where('slug', 'nigeria-rainfall-subnational')
            ->first();

        $this->assertNotNull($methodology);
        $this->assertNotNull($dataset);
        $this->assertSame('subnational_rainfall_indicators', $methodology->get('methodology_code'));
        $this->assertStringContainsString('Missing geography-period combinations', $methodology->get('body'));
        $this->assertContains($dataset->id(), $methodology->get('related_datasets'));
    }
}
