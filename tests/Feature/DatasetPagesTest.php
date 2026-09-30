<?php

namespace Tests\Feature;

use App\Models\Dataset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatasetPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public function test_dataset_index_page_loads(): void
    {
        $this->get('/explore')->assertRedirect('/downloads');

        $this->get('/datasets')->assertRedirect('/downloads');
    }

    public function test_search_page_loads_with_query_input(): void
    {
        $this->get('/search?q=rainfall')->assertRedirect('/downloads');
    }

    public function test_dataset_detail_page_loads(): void
    {
        $response = $this->get('/datasets/nigeria_country_year_indicators');

        $response
            ->assertOk()
            ->assertSee('Back to datasets')
            ->assertSee('About this dataset')
            ->assertSee('Available view')
            ->assertDontSee('Fact table');
    }

    public function test_dataset_detail_page_renders_editorial_metadata(): void
    {
        $response = $this->get('/datasets/nigeria_rainfall_subnational');

        $response
            ->assertOk()
            ->assertSee('Coverage and caveats')
            ->assertSee('Subnational rainfall records use canonical geography codes')
            ->assertSee('Missing or unavailable geography-period combinations must be shown as missing')
            ->assertSee('Plain-language labels for source codes')
            ->assertSee('Rainfall total for the selected 10-day dekad')
            ->assertSee('Source and citation')
            ->assertSee('World Food Programme');
    }

    public function test_dataset_detail_page_hides_api_disabled_datasets(): void
    {
        Dataset::query()
            ->where('code', 'nigeria_country_year_indicators')
            ->update(['api_enabled' => false]);

        $this->get('/datasets/nigeria_country_year_indicators')
            ->assertNotFound();
    }

    public function test_home_page_loads(): void
    {
        Dataset::query()
            ->where('code', 'climatewatch_historical_emissions')
            ->update(['download_count' => 1248]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Nigeria climate intelligence')
            ->assertSee('Latest monthly rainfall across Nigeria')
            ->assertSee('Monthly rainfall condition')
            ->assertSee('Emissions coverage map')
            ->assertSee('Nigeria in global energy-sector emissions')
            ->assertSee('Latest reported total')
            ->assertSee('Rank among published countries')
            ->assertSee('Latest data')
            ->assertSee('Explore data')
            ->assertSee('Topics')
            ->assertSee('Downloads')
            ->assertSee('1,248')
            ->assertSee('Unclassified')
            ->assertDontSee('all-time downloads')
            ->assertDontSee('Coverage')
            ->assertSee('Climate Watch Historical Emissions')
            ->assertSee('Find emissions, rainfall, flood, risk, policy, finance, and environmental data from one public data portal.');
    }

    public function test_public_theme_pages_load_related_database_backed_datasets(): void
    {
        $response = $this->get('/rainfall');

        $response
            ->assertOk()
            ->assertSee('Rainfall across Nigeria')
            ->assertSee('Where rainfall was higher or lower')
            ->assertSee('State map')
            ->assertSee('Reporting dekad')
            ->assertSee('The colours compare rainfall between places');
    }

    public function test_all_public_theme_pages_load_with_required_state_language(): void
    {
        $this->get('/rainfall')
            ->assertOk()
            ->assertSee('Where rainfall was higher or lower');

        foreach ([
            '/floods' => 'Where floods have occurred',
            '/risk' => 'Climate risk across Nigerian LGAs',
            '/policy' => 'Climate commitments and policies',
            '/finance' => 'Where climate finance is going',
            '/agriculture' => 'Agriculture, land and water',
            '/environment' => "Nigeria's environment in data",
        ] as $uri => $topic) {
            $this->get($uri)
                ->assertOk()
                ->assertSee($topic)
                ->assertSee('Data ingestion under development')
                ->assertDontSee('Filters');
        }

        $this->get('/emissions')
            ->assertOk()
            ->assertSee('Global emissions')
            ->assertSee('Reporting years')
            ->assertSee('x-model.number="yearStartIndex"', false)
            ->assertSee('x-model="selectedSector"', false)
            ->assertDontSee('All available sectors')
            ->assertDontSee('Partial Coverage Alert')
            ->assertDontSee('Source, unit, period, and state context');
    }

    public function test_emissions_page_is_a_single_source_global_explorer(): void
    {
        $this->get('/emissions')
            ->assertOk()
            ->assertSee('Global emissions')
            ->assertSee('Published source')
            ->assertSee('Map')
            ->assertSee('emissions-country-matrix', false)
            ->assertSee('sortCountryMatrix', false)
            ->assertSee('Kyoto GHG')
            ->assertSee('View country trend')
            ->assertSee('Countries')
            ->assertSee('Last update')
            ->assertSee('x-model="selectedCountries"', false)
            ->assertSee('x-model="selectedSector"', false)
            ->assertSee('x-model="selectedGas"', false)
            ->assertSee('x-data="emissionsComparison', false)
            ->assertSee('@click="toggleCountry(series.code)"', false)
            ->assertDontSee('Choose an emissions dataset')
            ->assertDontSee('Nigeria Climate TRACE Emissions and Air Pollutants')
            ->assertDontSee('Development fixture data is shown for interface testing');

        $this->get('/emissions?dataset=climatewatch_historical_emissions&range=2010-2023&sector=energy')
            ->assertOk()
            ->assertSee('Global emissions')
            ->assertSee('Energy')
            ->assertDontSee('Nigeria state and city emissions');

        $this->get('/emissions?dataset=nigeria_climate_trace_emissions')
            ->assertOk()
            ->assertSee('Global emissions')
            ->assertDontSee('Nigeria state and city emissions');
    }

    public function test_downloads_and_topics_pages_load(): void
    {
        Dataset::query()
            ->where('code', 'climatewatch_historical_emissions')
            ->update(['download_count' => 12]);
        Dataset::query()
            ->where('code', 'climatewatch_adaptation_profile')
            ->update(['download_count' => 3]);

        $this->get('/downloads')
            ->assertOk()
            ->assertSee('Available datasets')
            ->assertSee('Topics')
            ->assertSee('Downloads')
            ->assertSee('Page 1 of 2')
            ->assertSee('Climate Watch Historical Emissions')
            ->assertSee('Download Climate Watch Adaptation Profile as CSV')
            ->assertDontSee('Country Year Indicator')
            ->assertDontSee('Event Impact')
            ->assertDontSee('View dataset details')
            ->assertDontSee('GET /api/datasets')
            ->assertDontSee('climate-data');

        $this->get('/downloads?sort=downloads&direction=desc')
            ->assertOk()
            ->assertSeeInOrder([
                'Climate Watch Historical Emissions',
                'Climate Watch Adaptation Profile',
            ]);

        $this->get('/downloads?page=2')
            ->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('Showing 11-20 of 20 datasets');

        $this->get('/topics')
            ->assertOk()
            ->assertSee('Explore climate topics')
            ->assertSee('Rainfall')
            ->assertDontSee('climate-data');

    }

    public function test_profile_and_story_routes_load_with_unavailable_states(): void
    {
        $this->get('/countries/NG')
            ->assertOk()
            ->assertSee('Nigeria climate profile')
            ->assertSee('Country map and exact profile values require the API and MAP handoffs')
            ->assertSee('Rainfall records available; map binding provisional')
            ->assertDontSee('climate-data');

        $this->get('/geographies/KD')
            ->assertOk()
            ->assertSee('KD climate profile')
            ->assertSee('Boundary highlight, parent hierarchy, and unavailable-measure states require the MAP-01 handoff')
            ->assertSee('Ward drilldown')
            ->assertSee('Unavailable pending INEC wardcode-to-PCODE crosswalk')
            ->assertDontSee('climate-data');

        $this->get('/stories/rainfall-and-floods')
            ->assertOk()
            ->assertSee('Rainfall And Floods')
            ->assertSee('Opening evidence')
            ->assertSee('without inventing narrative claims')
            ->assertDontSee('climate-data');
    }

    public function test_retired_about_and_methodology_routes_are_not_publicly_available(): void
    {
        $this->get('/about')->assertNotFound();
        $this->get('/methodologies/subnational_rainfall_indicators')->assertNotFound();
    }
}
