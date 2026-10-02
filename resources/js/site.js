import Alpine from 'alpinejs';
import AOS from 'aos';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import 'aos/dist/aos.css';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const datasetTypeAssets = {
    country_year_sector_gas: {
        name: 'Country-Year Sector Gas',
        image: 'https://images.unsplash.com/photo-1473448912268-2022ce9509d8?auto=format&fit=crop&w=1200&q=80',
    },
    admin_period_indicator: {
        name: 'Admin Period Indicator',
        image: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80',
    },
    country_year_indicator: {
        name: 'Country-Year Indicator',
        image: 'https://images.unsplash.com/photo-1466611653911-95081537e5b7?auto=format&fit=crop&w=1200&q=80',
    },
    country_document: {
        name: 'Country Document',
        image: 'https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=1200&q=80',
    },
    document_sector_response: {
        name: 'Document Sector Response',
        image: 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1200&q=80',
    },
    event_impact: {
        name: 'Event Impact',
        image: 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1200&q=80',
    },
};

async function fetchJson(url) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json();
}

function mapCoordinates(geometry, callback) {
    if (!geometry || !geometry.type) {
        return;
    }

    const visit = (coordinates) => {
        if (!Array.isArray(coordinates)) {
            return;
        }

        if (typeof coordinates[0] === 'number' && typeof coordinates[1] === 'number') {
            callback(coordinates);

            return;
        }

        coordinates.forEach(visit);
    };

    if (geometry.type === 'GeometryCollection') {
        (geometry.geometries || []).forEach((child) => mapCoordinates(child, callback));

        return;
    }

    visit(geometry.coordinates);
}

function mapPath(geometry, project) {
    const line = (coordinates) => coordinates
        .map(([longitude, latitude], index) => {
            const [x, y] = project(longitude, latitude);

            return `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
    const polygon = (rings) => rings.map((ring) => `${line(ring)} Z`).join(' ');

    switch (geometry?.type) {
        case 'Polygon':
            return polygon(geometry.coordinates);
        case 'MultiPolygon':
            return geometry.coordinates.map(polygon).join(' ');
        case 'LineString':
            return line(geometry.coordinates);
        case 'MultiLineString':
            return geometry.coordinates.map(line).join(' ');
        default:
            return '';
    }
}

Alpine.data('summaryCarousel', () => ({
    activeSlide: 0,
    totalSlides: 2,
    previous() {
        this.activeSlide = (this.activeSlide - 1 + this.totalSlides) % this.totalSlides;
    },
    next() {
        this.activeSlide = (this.activeSlide + 1) % this.totalSlides;
    },
}));

Alpine.data('homeHeroCarousel', (slideCount = 1) => ({
    activeSlide: 0,
    totalSlides: Math.max(1, Number(slideCount) || 1),
    timer: null,
    init() {
        if (this.totalSlides < 2 || prefersReducedMotion.matches) {
            return;
        }

        this.timer = window.setInterval(() => {
            this.activeSlide = (this.activeSlide + 1) % this.totalSlides;
        }, 7000);
    },
    destroy() {
        if (this.timer) {
            window.clearInterval(this.timer);
        }
    },
}));

Alpine.data('rainfallNormalPreview', () => ({
    loaded: false,
    loading: false,
    error: false,
    mapMarkup: '',
    latestAverage: null,
    seasonalNormal: null,
    differencePercent: null,
    async load() {
        if (this.loaded || this.loading) {
            return;
        }

        this.loading = true;

        try {
            const response = await fetchJson('/api/maps/datasets/nigeria_rainfall_subnational/choropleth?geography_level=1&indicator_code=r1h&comparison_indicator_code=r1h_avg&period_type=dekad&boundary_version=v01&simplification=public-1mb');
            const features = response.features || [];
            const withValues = features.filter((feature) => Number.isFinite(Number(feature.properties?.value)) && Number.isFinite(Number(feature.properties?.comparison_value)) && Number(feature.properties.comparison_value) > 0);

            if (withValues.length === 0) {
                throw new Error('No comparable monthly rainfall values are available.');
            }

            this.latestAverage = withValues.reduce((total, feature) => total + Number(feature.properties.value), 0) / withValues.length;
            this.seasonalNormal = withValues.reduce((total, feature) => total + Number(feature.properties.comparison_value), 0) / withValues.length;
            this.differencePercent = ((this.latestAverage - this.seasonalNormal) / this.seasonalNormal) * 100;
            this.mapMarkup = this.buildMap(features);
            this.loaded = true;
        } catch (error) {
            this.error = true;
        } finally {
            this.loading = false;
        }
    },
    buildMap(features) {
        const bounds = { minX: Infinity, minY: Infinity, maxX: -Infinity, maxY: -Infinity };

        features.forEach((feature) => mapCoordinates(feature.geometry, ([longitude, latitude]) => {
            bounds.minX = Math.min(bounds.minX, longitude);
            bounds.maxX = Math.max(bounds.maxX, longitude);
            bounds.minY = Math.min(bounds.minY, latitude);
            bounds.maxY = Math.max(bounds.maxY, latitude);
        }));

        const width = 600;
        const height = 340;
        const padding = 12;
        const scale = Math.min(
            (width - (padding * 2)) / Math.max(1, bounds.maxX - bounds.minX),
            (height - (padding * 2)) / Math.max(1, bounds.maxY - bounds.minY),
        );
        const mapWidth = (bounds.maxX - bounds.minX) * scale;
        const mapHeight = (bounds.maxY - bounds.minY) * scale;
        const offsetX = (width - mapWidth) / 2;
        const offsetY = (height - mapHeight) / 2;
        const project = (longitude, latitude) => [
            offsetX + ((longitude - bounds.minX) * scale),
            offsetY + ((bounds.maxY - latitude) * scale),
        ];

        return features.map((feature) => {
            const properties = feature.properties || {};
            const current = Number(properties.value);
            const normal = Number(properties.comparison_value);
            const change = normal > 0 && Number.isFinite(current) ? (current - normal) / normal : null;
            const fill = change === null
                ? '#d9dddb'
                : change >= 0.15 ? '#00796b'
                    : change <= -0.15 ? '#e7b45f'
                        : '#9bcfca';
            const period = properties.period_date || 'latest reporting date';
            const summary = change === null
                ? 'No comparable value'
                : `${Math.round(change * 100)}% ${change >= 0 ? 'above' : 'below'} normal`;
            const label = `${properties.geography_name || 'State'}: ${summary}, ${period}`
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
            const path = mapPath(feature.geometry, project);

            return `<path d="${path}" fill="${fill}" stroke="#ffffff" stroke-width="0.8"><title>${label}</title></path>`;
        }).join('');
    },
    metric(value) {
        return Number.isFinite(value) ? `${Math.round(value)} mm` : 'Not available';
    },
    differenceLabel() {
        if (!Number.isFinite(this.differencePercent)) {
            return 'Not available';
        }

        return `${this.differencePercent >= 0 ? '+' : ''}${Math.round(this.differencePercent)}%`;
    },
    differenceDescription() {
        if (!Number.isFinite(this.differencePercent)) {
            return 'against seasonal norm';
        }

        return this.differencePercent >= 0 ? 'above normal' : 'below normal';
    },
}));

Alpine.data('globalEmissionsPreview', (points = []) => ({
    mapMarkup: '',
    async init() {
        try {
            const response = await fetch('/maps/world-countries.geojson', {
                headers: { Accept: 'application/geo+json, application/json' },
            });

            if (!response.ok) {
                throw new Error(`Map request failed with status ${response.status}`);
            }

            const world = await response.json();
            const values = new Map(points.map((point) => [String(point.code), point]));
            const publishedValues = points
                .map((point) => Number(point.value) || 0)
                .filter((value) => value > 0);
            const minimum = Math.min(...publishedValues);
            const maximum = Math.max(...publishedValues);

            this.mapMarkup = (world.features || []).map((feature) => {
                const point = values.get(String(feature.properties?.code || ''));
                const label = point
                    ? `${point.country}: ${new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 }).format(point.value)}`
                    : `${feature.properties?.name || 'Country'}: no published value`;
                const fill = point ? this.colorForValue(point.value, minimum, maximum) : '#dce3df';
                const path = mapPath(feature.geometry, (longitude, latitude) => [
                    ((longitude + 180) / 360) * 720,
                    ((90 - latitude) / 180) * 360,
                ]);

                const isNigeria = point?.code === 'NGA';
                return `<path d="${path}" fill="${fill}" stroke="${isNigeria ? '#063f39' : '#ffffff'}" stroke-width="${isNigeria ? '1.5' : '0.55'}"><title>${label.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')}</title></path>`;
            }).join('');
        } catch (error) {
            this.mapMarkup = '<text x="360" y="180" text-anchor="middle" fill="#4d5652" font-size="16">Global map preview unavailable.</text>';
        }
    },
    colorForValue(value, minimum, maximum) {
        const numericValue = Math.max(minimum, Number(value) || minimum);
        const intensity = maximum === minimum
            ? 1
            : (Math.log(numericValue) - Math.log(minimum)) / (Math.log(maximum) - Math.log(minimum));
        const red = Math.round(194 - (188 * intensity));
        const green = Math.round(229 - (139 * intensity));
        const blue = Math.round(219 - (131 * intensity));

        return `rgb(${red} ${green} ${blue})`;
    },
}));

Alpine.data('coverageMap', () => ({
    loading: true,
    error: '',
    datasetKey: 'rainfall',
    datasetOptions: [
        {
            key: 'rainfall',
            label: 'Rainfall sample',
            datasetCode: 'nigeria_rainfall_subnational',
            indicatorCode: 'rfh',
            periodType: 'dekad',
            title: 'State rainfall sample',
            color: '#0077b6',
        },
        {
            key: 'risk',
            label: 'Flood exposure sample',
            datasetCode: 'nigeria_risk_assessment_indicators',
            indicatorCode: 'flood_exposure_score',
            periodType: 'assessment',
            title: 'State flood exposure sample',
            color: '#b45309',
        },
    ],
    activeLevel: 1,
    parentCode: null,
    features: [],
    selectedFeature: null,
    meta: null,
    viewBox: '0 0 720 380',
    async init() {
        await this.loadMap(1);
    },
    get currentDataset() {
        return this.datasetOptions.find((option) => option.key === this.datasetKey) || this.datasetOptions[0];
    },
    get mapTransform() {
        return 'translate(-72 -30)';
    },
    get mapLabel() {
        if (this.loading) {
            return 'Loading published state boundaries';
        }

        if (this.error) {
            return 'Published boundary map unavailable';
        }

        return this.activeLevel === 1
            ? `${this.currentDataset.title}. Select a state to view LGAs.`
            : `${this.features[0]?.properties.parent_name || 'Selected state'} LGA ${this.currentDataset.title.toLowerCase()}. Select All states to return.`;
    },
    get friendlyMeasureName() {
        const labels = {
            rfh: 'Rainfall in this 10-day period',
            rfh_avg: 'Typical rainfall for this 10-day period',
            r1h: 'Rainfall in the previous month',
            r1h_avg: 'Typical rainfall for the previous month',
            r3h: 'Rainfall in the previous three months',
            r3h_avg: 'Typical rainfall for the previous three months',
            rfq: 'Rainfall compared with what is usual this 10-day period',
            r1q: 'Rainfall compared with what is usual over the previous month',
            r3q: 'Rainfall compared with what is usual over the previous three months',
        };

        return labels[String(this.selectedIndicator || '')] || this.selectedMeasure?.label || 'Rainfall';
    },
    get selectedMeasureDescription() {
        if (String(this.selectedIndicator || '').endsWith('q')) {
            return 'This measure compares rainfall with what is usual for the selected period. 100% means typical rainfall; below 100% means less than usual, while above 100% means more than usual.';
        }

        return `${this.friendlyMeasureName} is measured in millimetres. The map lets you compare the recorded amount between places for the selected date.`;
    },
    get boundaryCountLabel() {
        const area = this.activeLevel === 1 ? 'states and FCT' : 'LGAs';

        return `${this.features.length} ${area}`;
    },
    get selectedValueTitle() {
        if (String(this.selectedIndicator || '').endsWith('q')) {
            return 'Rainfall compared with what is usual';
        }

        return this.friendlyMeasureName;
    },
    get selectedValueExplanation() {
        if (!this.selectedFeature || this.selectedFeature.value_status !== 'value') {
            return 'No rainfall value was reported for this place and period.';
        }

        if (String(this.selectedIndicator || '').endsWith('q')) {
            const difference = this.selectedFeature.value - 100;
            const comparison = Math.abs(difference) < 10
                ? 'close to what is usual'
                : difference < 0 ? `${this.formatValue(Math.abs(difference))}% below what is usual` : `${this.formatValue(difference)}% above what is usual`;

            return `This is ${comparison} for this period. It does not by itself show drought, flooding, or impacts.`;
        }

        const usual = this.selectedFeature.comparison_value;
        if (Number.isFinite(usual) && usual > 0) {
            const difference = ((this.selectedFeature.value - usual) / usual) * 100;
            const comparison = Math.abs(difference) < 10
                ? `close to the usual ${this.formatValue(usual)} ${this.selectedFeature.comparison_unit || this.selectedMeasure?.unit || ''}`
                : difference < 0
                    ? `${this.formatValue(Math.abs(difference))}% less than the usual ${this.formatValue(usual)} ${this.selectedFeature.comparison_unit || this.selectedMeasure?.unit || ''}`
                    : `${this.formatValue(difference)}% more than the usual ${this.formatValue(usual)} ${this.selectedFeature.comparison_unit || this.selectedMeasure?.unit || ''}`;

            return `${this.selectedFeature.geography_name} recorded ${this.formatValue(this.selectedFeature.value)} ${this.selectedMeasure?.unit || ''} of rain for the period ending ${this.formatDate(this.selectedFeature.period_date)}. That is ${comparison} for this time of year. It does not by itself show drought, flooding, or impacts.`;
        }

        if (String(this.selectedIndicator || '').endsWith('_avg')) {
            return `This is the long-term average rainfall for this time of year, calculated from past records. It is a benchmark, not the rainfall measured on this date.`;
        }

        return `${this.selectedFeature.geography_name} recorded ${this.formatValue(this.selectedFeature.value)} ${this.selectedMeasure?.unit || ''} of rain for the period ending ${this.formatDate(this.selectedFeature.period_date)}.`;
    },
    get selectedValueLabel() {
        if (!this.selectedFeature) {
            return '';
        }

        const properties = this.selectedFeature;

        return properties.value_status === 'value'
            ? `${properties.value} ${properties.unit || ''} · ${properties.period_date || 'Current period'}`.trim()
            : 'No value reported for the selected period';
    },
    get mapMarkup() {
        return this.features.map((feature) => {
            const code = feature.properties.geography_code;
            const label = this.featureLabel(feature).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

            return `<path d="${feature.path}" fill="${feature.fill}" stroke="#f8faf9" stroke-width="0.75" data-geography-code="${code}" aria-label="${label}" class="cursor-pointer transition-opacity hover:opacity-75"><title>${label}</title></path>`;
        }).join('');
    },
    async loadMap(level, parentCode = null) {
        this.loading = true;
        this.error = '';
        this.selectedFeature = null;
        this.activeLevel = level;
        this.parentCode = parentCode;

        const params = new URLSearchParams({
            geography_level: String(level),
            indicator_code: this.currentDataset.indicatorCode,
            period_type: this.currentDataset.periodType,
            boundary_version: 'v01',
            simplification: 'public-1mb',
        });
        const comparisonIndicators = { rfh: 'rfh_avg', r1h: 'r1h_avg', r3h: 'r3h_avg' };

        if (comparisonIndicators[this.selectedIndicator]) {
            params.set('comparison_indicator_code', comparisonIndicators[this.selectedIndicator]);
        }

        if (parentCode) {
            params.set('parent_code', parentCode);
        }

        try {
            const payload = await fetchJson(`/api/maps/datasets/${this.currentDataset.datasetCode}/choropleth?${params.toString()}`);
            this.meta = payload.meta;
            this.features = this.projectFeatures(payload.features || []);

            if (!this.features.length) {
                this.error = 'No published boundaries are available for this selection.';
            }
        } catch (requestError) {
            console.error(requestError);
            this.error = 'The published map could not be loaded. Try another available map dataset.';
        } finally {
            this.loading = false;
        }
    },
    projectFeatures(features) {
        const bounds = { minLongitude: Infinity, maxLongitude: -Infinity, minLatitude: Infinity, maxLatitude: -Infinity };

        features.forEach((feature) => mapCoordinates(feature.geometry, ([longitude, latitude]) => {
            bounds.minLongitude = Math.min(bounds.minLongitude, longitude);
            bounds.maxLongitude = Math.max(bounds.maxLongitude, longitude);
            bounds.minLatitude = Math.min(bounds.minLatitude, latitude);
            bounds.maxLatitude = Math.max(bounds.maxLatitude, latitude);
        }));

        if (!Number.isFinite(bounds.minLongitude)) {
            return [];
        }

        const width = 720;
        const height = 380;
        const padding = 42;
        const longitudeSpan = Math.max(bounds.maxLongitude - bounds.minLongitude, 0.0001);
        const latitudeSpan = Math.max(bounds.maxLatitude - bounds.minLatitude, 0.0001);
        const scale = Math.min((width - (padding * 2)) / longitudeSpan, (height - (padding * 2)) / latitudeSpan);
        const contentWidth = longitudeSpan * scale;
        const contentHeight = latitudeSpan * scale;
        const offsetX = ((width - contentWidth) / 2) - (bounds.minLongitude * scale);
        const offsetY = ((height - contentHeight) / 2) + (bounds.maxLatitude * scale);
        const project = (longitude, latitude) => [(longitude * scale) + offsetX, (-latitude * scale) + offsetY];

        return features.map((feature) => ({
            ...feature,
            path: mapPath(feature.geometry, project),
            fill: feature.properties.value_status === 'value' ? this.currentDataset.color : '#cfd8d4',
        }));
    },
    featureLabel(feature) {
        const properties = feature.properties;

        return properties.value_status === 'value'
            ? `${properties.geography_name}: ${properties.value} ${properties.unit || ''} for ${properties.period_date || 'the selected period'}`
            : `${properties.geography_name}: no value reported for the selected period`;
    },
    drillDown(feature) {
        this.selectedFeature = feature.properties;

        if (this.activeLevel === 1) {
            this.loadMap(2, feature.properties.geography_code);
        }
    },
    handleMapClick(event) {
        const code = event.target.closest('[data-geography-code]')?.dataset.geographyCode;
        const feature = this.features.find((item) => item.properties.geography_code === code);

        if (feature) {
            this.drillDown(feature);
        }
    },
    selectDataset() {
        this.loadMap(1);
    },
}));

Alpine.data('rainfallExplorer', (config = {}) => ({
    indicators: config.indicators || [],
    periods: config.periods || [],
    selectedIndicator: config.default_indicator || 'rfh',
    selectedPeriod: config.default_period || null,
    sourceName: config.source_name || '',
    recordCount: config.record_count || 0,
    activeLevel: 1,
    parentCode: null,
    selectedState: null,
    selectedFeature: null,
    features: [],
    loading: false,
    error: '',
    get selectedMeasure() {
        return this.indicators.find((indicator) => indicator.code === this.selectedIndicator) || null;
    },
    measureLabel() {
        const labels = {
            rfh: 'Rainfall in this 10-day period',
            rfh_avg: 'Typical rainfall for this 10-day period',
            r1h: 'Rainfall in the previous month',
            r1h_avg: 'Typical rainfall for the previous month',
            r3h: 'Rainfall in the previous three months',
            r3h_avg: 'Typical rainfall for the previous three months',
            rfq: 'Rainfall compared with what is usual this 10-day period',
            r1q: 'Rainfall compared with what is usual over the previous month',
            r3q: 'Rainfall compared with what is usual over the previous three months',
        };

        return labels[String(this.selectedIndicator || '')] || this.selectedMeasure?.label || 'Rainfall';
    },
    measureDescription() {
        if (String(this.selectedIndicator || '').endsWith('q')) {
            return 'This measure compares rainfall with what is usual for the selected period. 100% means typical rainfall; below 100% means less than usual, while above 100% means more than usual.';
        }

        return `${this.measureLabel()} is measured in millimetres. The map lets you compare the recorded amount between places for the selected date.`;
    },
    selectedValueTitle() {
        return String(this.selectedIndicator || '').endsWith('q')
            ? 'Rainfall compared with what is usual'
            : this.measureLabel();
    },
    selectedValueExplanation() {
        if (!this.selectedFeature || this.selectedFeature.value_status !== 'value') {
            return 'No rainfall value was reported for this place and period.';
        }

        if (String(this.selectedIndicator || '').endsWith('q')) {
            const difference = Number(this.selectedFeature.value) - 100;
            const comparison = Math.abs(difference) < 10
                ? 'close to what is usual'
                : difference < 0
                    ? `${this.formatValue(Math.abs(difference))}% below what is usual`
                    : `${this.formatValue(difference)}% above what is usual`;

            return `This is ${comparison} for this period. It does not by itself show drought, flooding, or impacts.`;
        }

        const usual = Number(this.selectedFeature.comparison_value);
        const unit = this.selectedFeature.comparison_unit || this.selectedMeasure?.unit || '';
        if (Number.isFinite(usual) && usual > 0) {
            const difference = ((Number(this.selectedFeature.value) - usual) / usual) * 100;
            const comparison = Math.abs(difference) < 10
                ? `close to the usual ${this.formatValue(usual)} ${unit}`
                : difference < 0
                    ? `${this.formatValue(Math.abs(difference))}% less than the usual ${this.formatValue(usual)} ${unit}`
                    : `${this.formatValue(difference)}% more than the usual ${this.formatValue(usual)} ${unit}`;

            return `${this.selectedFeature.geography_name} recorded ${this.formatValue(this.selectedFeature.value)} ${this.selectedMeasure?.unit || ''} of rain for the period ending ${this.formatDate(this.selectedFeature.period_date)}. That is ${comparison} for this time of year. It does not by itself show drought, flooding, or impacts.`;
        }

        if (String(this.selectedIndicator || '').endsWith('_avg')) {
            return 'This is the long-term average rainfall for this time of year, calculated from past records. It is a benchmark, not the rainfall measured on this date.';
        }

        return `${this.selectedFeature.geography_name} recorded ${this.formatValue(this.selectedFeature.value)} ${this.selectedMeasure?.unit || ''} of rain for the period ending ${this.formatDate(this.selectedFeature.period_date)}.`;
    },
    get mapLabel() {
        return this.loading
            ? 'Loading Nigeria rainfall map'
            : `${this.selectedMeasure?.label || 'Rainfall'} map for ${this.selectedPeriod || 'the selected period'}`;
    },
    get mapMarkup() {
        return this.features.map((feature) => {
            const properties = feature.properties;
            const label = this.escape(`${properties.geography_name}: ${properties.value_status === 'value' ? `${this.formatValue(properties.value)} ${properties.unit || ''}` : 'No value reported'}`);

            return `<path d="${feature.path}" fill="${feature.fill}" stroke="#ffffff" stroke-width="0.7" vector-effect="non-scaling-stroke" data-geography-code="${this.escape(properties.geography_code)}" aria-label="${label}" class="cursor-pointer transition-opacity hover:opacity-75"><title>${label}</title></path>`;
        }).join('');
    },
    async init() {
        window.addEventListener('hashchange', () => this.syncViewFromHash());
        if (this.selectedPeriod && this.selectedIndicator) {
            const requestedHash = window.location.hash;
            const lgaMatch = requestedHash.match(/^#lga-map\/([A-Za-z0-9_-]+)$/);

            if (!lgaMatch && requestedHash !== '#state-map') {
                window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#state-map`);
            }

            if (lgaMatch) {
                const stateCode = decodeURIComponent(lgaMatch[1]);
                await this.loadMap(1, null, false);
                this.selectedState = this.features.find((feature) => feature.properties.geography_code === stateCode)?.properties ?? null;

                if (this.selectedState) {
                    await this.loadMap(2, stateCode, false);
                } else {
                    window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#state-map`);
                }
            } else {
                await this.loadMap(1, null, false);
            }
        }
    },
    async syncViewFromHash() {
        const hash = window.location.hash;
        if (hash === '#state-map') {
            if (this.activeLevel !== 1) await this.loadMap(1, null, false);
            return;
        }

        const lgaMatch = hash.match(/^#lga-map\/([A-Za-z0-9_-]+)$/);
        if (!lgaMatch) {
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#state-map`);
            if (this.activeLevel !== 1) await this.loadMap(1, null, false);
            return;
        }

        const stateCode = decodeURIComponent(lgaMatch[1]);
        if (this.activeLevel === 2 && this.parentCode === stateCode) return;
        if (this.selectedState?.geography_code !== stateCode) {
            await this.loadMap(1, null, false);
            this.selectedState = this.features.find((feature) => feature.properties.geography_code === stateCode)?.properties ?? null;
        }

        if (this.selectedState) {
            await this.loadMap(2, stateCode, false);
        } else {
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#state-map`);
        }
    },
    async loadMap(level = 1, parentCode = null, updateUrl = true) {
        if (!this.selectedPeriod || !this.selectedIndicator) {
            return;
        }

        if (updateUrl) {
            const hash = level === 2 && parentCode ? `#lga-map/${encodeURIComponent(parentCode)}` : '#state-map';
            if (window.location.hash !== hash) window.location.hash = hash;
        }

        const selectedStateCode = level === 1 ? this.selectedState?.geography_code : null;
        this.loading = true;
        this.error = '';
        this.activeLevel = level;
        this.parentCode = parentCode;
        this.selectedFeature = null;
        const params = new URLSearchParams({
            geography_level: String(level),
            indicator_code: this.selectedIndicator,
            period_type: 'dekad',
            date_from: this.selectedPeriod,
            date_to: this.selectedPeriod,
            boundary_version: 'v01',
            simplification: 'public-1mb',
        });

        const comparisonIndicators = { rfh: 'rfh_avg', r1h: 'r1h_avg', r3h: 'r3h_avg' };
        if (comparisonIndicators[this.selectedIndicator]) {
            params.set('comparison_indicator_code', comparisonIndicators[this.selectedIndicator]);
        }

        if (parentCode) {
            params.set('parent_code', parentCode);
        }

        try {
            const payload = await fetchJson(`/api/maps/datasets/nigeria_rainfall_subnational/choropleth?${params.toString()}`);
            this.features = this.projectFeatures(payload.features || []);
            if (selectedStateCode) {
                const restoredState = this.features.find((feature) => feature.properties.geography_code === selectedStateCode);
                if (restoredState) {
                    this.selectedState = restoredState.properties;
                    this.selectedFeature = restoredState.properties;
                }
            }
            if (!this.features.length) {
                this.error = 'No published boundaries are available for this selection.';
            }
        } catch (error) {
            console.error(error);
            this.features = [];
            this.error = 'The rainfall map could not be loaded for this selection.';
        } finally {
            this.loading = false;
        }
    },
    projectFeatures(features) {
        const bounds = { minLongitude: Infinity, maxLongitude: -Infinity, minLatitude: Infinity, maxLatitude: -Infinity };
        const values = features.map((feature) => feature.properties?.value).filter((value) => Number.isFinite(value));
        const minimum = Math.min(...values);
        const maximum = Math.max(...values);

        features.forEach((feature) => mapCoordinates(feature.geometry, ([longitude, latitude]) => {
            bounds.minLongitude = Math.min(bounds.minLongitude, longitude);
            bounds.maxLongitude = Math.max(bounds.maxLongitude, longitude);
            bounds.minLatitude = Math.min(bounds.minLatitude, latitude);
            bounds.maxLatitude = Math.max(bounds.maxLatitude, latitude);
        }));

        if (!Number.isFinite(bounds.minLongitude)) {
            return [];
        }

        const width = 720;
        const height = 380;
        const padding = 18;
        const longitudeSpan = Math.max(bounds.maxLongitude - bounds.minLongitude, 0.0001);
        const latitudeSpan = Math.max(bounds.maxLatitude - bounds.minLatitude, 0.0001);
        const scale = Math.min((width - (padding * 2)) / longitudeSpan, (height - (padding * 2)) / latitudeSpan);
        const offsetX = ((width - (longitudeSpan * scale)) / 2) - (bounds.minLongitude * scale);
        const offsetY = ((height - (latitudeSpan * scale)) / 2) + (bounds.maxLatitude * scale);
        const project = (longitude, latitude) => [(longitude * scale) + offsetX, (-latitude * scale) + offsetY];

        return features.map((feature) => ({
            ...feature,
            path: mapPath(feature.geometry, project),
            fill: feature.properties.value_status === 'value'
                ? this.valueColor(feature.properties.value, minimum, maximum)
                : '#d7dfdc',
        }));
    },
    valueColor(value, minimum, maximum) {
        const fraction = maximum === minimum ? 0.65 : (value - minimum) / (maximum - minimum);
        const start = [225, 241, 229];
        const end = [0, 121, 107];
        const channel = (index) => Math.round(start[index] + ((end[index] - start[index]) * fraction));

        return `rgb(${channel(0)}, ${channel(1)}, ${channel(2)})`;
    },
    handleMapClick(event) {
        const code = event.target.closest('[data-geography-code]')?.dataset.geographyCode;
        const feature = this.features.find((item) => item.properties.geography_code === code);

        if (!feature) {
            return;
        }

        this.selectedFeature = feature.properties;
        if (this.activeLevel === 1) {
            this.selectedState = feature.properties;
        }
    },
    formatValue(value) {
        if (value === null || value === undefined) {
            return 'Not reported';
        }

        return new Intl.NumberFormat('en-NG', { maximumFractionDigits: 1 }).format(value);
    },
    formatDate(value) {
        if (!value) {
            return 'No period';
        }

        return new Intl.DateTimeFormat('en-NG', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
    },
    escape(value) {
        return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
    },
}));

Alpine.data('disclosure', (initialOpen = false) => ({
    open: initialOpen,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
}));

Alpine.data('datasetExplorer', (config = {}) => ({
    datasetCode: config.datasetCode || null,
    loadingCatalog: false,
    loadingRecords: false,
    loadingLookups: false,
    dataset: null,
    datasets: [],
    records: [],
    lookups: {
        dataset_types: [],
        sources: [],
        sectors: [],
        gases: [],
        indicators: [],
        geographies: [],
        document_types: [],
    },
    catalogFilters: {
        query: '',
        dataset_type: '',
        source_code: '',
    },
    filters: {
        country_code: '',
        geography_code: '',
        indicator_code: '',
        sector_code: '',
        gas_code: '',
        year: '',
        period_type: '',
        date_from: '',
        date_to: '',
        status: '',
        document_type_code: '',
        document_code: '',
        question_code: '',
        event_type: '',
        metric_code: '',
    },
    pagination: {
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 1,
    },
    async init() {
        if (!this.datasetCode) {
            const params = new URLSearchParams(window.location.search);
            this.catalogFilters.query = params.get('q') || '';
            this.catalogFilters.dataset_type = params.get('dataset_type') || '';
            this.catalogFilters.source_code = params.get('source_code') || '';
        }

        await this.loadLookups();

        if (this.datasetCode) {
            await this.loadDataset();
            await this.loadRecords();

            return;
        }

        await this.loadCatalog();
    },
    async loadLookups() {
        this.loadingLookups = true;

        try {
            const payload = await fetchJson('/api/lookups');
            this.lookups = payload.data;
        } catch (error) {
            console.error(error);
        } finally {
            this.loadingLookups = false;
        }
    },
    async loadCatalog() {
        this.loadingCatalog = true;

        try {
            const payload = await fetchJson('/api/datasets');
            this.datasets = payload.data;
        } catch (error) {
            console.error(error);
        } finally {
            this.loadingCatalog = false;
        }
    },
    async loadDataset() {
        if (!this.datasetCode) {
            return;
        }

        try {
            const payload = await fetchJson(`/api/datasets/${this.datasetCode}`);
            this.dataset = payload.data;
        } catch (error) {
            console.error(error);
        }
    },
    async loadRecords(page = 1) {
        if (!this.datasetCode) {
            return;
        }

        this.loadingRecords = true;

        try {
            const params = new URLSearchParams({
                page: String(page),
                per_page: String(this.pagination.per_page),
            });

            Object.entries(this.filters).forEach(([key, value]) => {
                if (value !== null && value !== '') {
                    params.set(key, String(value));
                }
            });

            const payload = await fetchJson(`/api/datasets/${this.datasetCode}/records?${params.toString()}`);
            this.records = payload.data;
            this.pagination = payload.meta.pagination;
        } catch (error) {
            console.error(error);
            this.records = [];
        } finally {
            this.loadingRecords = false;
        }
    },
    lookupOptions(key) {
        return this.lookups[key] || [];
    },
    datasetTypeLabel(type) {
        return datasetTypeAssets[type]?.name
            || this.lookupOptions('dataset_types').find((item) => item.code === type)?.name
            || type
            || 'Dataset';
    },
    datasetViewLabel(type) {
        switch (type) {
            case 'country_year_sector_gas':
                return 'Time series by sector and gas';
            case 'admin_period_indicator':
                return 'Geography-period indicator records';
            case 'country_year_indicator':
                return 'Country-year indicator records';
            case 'country_document':
                return 'Document list and metadata';
            case 'document_sector_response':
                return 'Sector response text records';
            case 'event_impact':
                return 'Event impact records';
            default:
                return 'Dataset records';
        }
    },
    datasetImage(type) {
        return datasetTypeAssets[type]?.image
            || 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1200&q=80';
    },
    filteredDatasets() {
        const query = this.catalogFilters.query.trim().toLowerCase();

        return this.datasets.filter((dataset) => {
            if (query && ![
                dataset.code,
                dataset.title,
                dataset.summary,
                dataset.dataset_type,
                dataset.source?.name,
                dataset.source?.code,
            ].some((value) => String(value || '').toLowerCase().includes(query))) {
                return false;
            }

            if (this.catalogFilters.dataset_type && dataset.dataset_type !== this.catalogFilters.dataset_type) {
                return false;
            }

            if (this.catalogFilters.source_code && dataset.source?.code !== this.catalogFilters.source_code) {
                return false;
            }

            return true;
        });
    },
    totalSeededRows() {
        return this.filteredDatasets()
            .reduce((total, dataset) => total + (dataset.latest_version?.row_count || 0), 0);
    },
    activeFilterFields() {
        switch (this.dataset?.dataset_type) {
            case 'country_year_sector_gas':
                return [
                    { key: 'country_code', label: 'Country code', type: 'text' },
                    { key: 'year', label: 'Year', type: 'number' },
                    { key: 'sector_code', label: 'Sector', type: 'select', lookup: 'sectors' },
                    { key: 'gas_code', label: 'Gas', type: 'select', lookup: 'gases' },
                ];
            case 'admin_period_indicator':
                return [
                    { key: 'geography_code', label: 'Geography', type: 'select', lookup: 'geographies' },
                    { key: 'indicator_code', label: 'Indicator', type: 'select', lookup: 'indicators' },
                    { key: 'period_type', label: 'Period type', type: 'text' },
                    { key: 'date_from', label: 'Date from', type: 'date' },
                    { key: 'date_to', label: 'Date to', type: 'date' },
                ];
            case 'country_year_indicator':
                return [
                    { key: 'country_code', label: 'Country code', type: 'text' },
                    { key: 'indicator_code', label: 'Indicator', type: 'select', lookup: 'indicators' },
                    { key: 'year', label: 'Year', type: 'number' },
                ];
            case 'country_document':
                return [
                    { key: 'country_code', label: 'Country code', type: 'text' },
                    { key: 'document_type_code', label: 'Document type', type: 'select', lookup: 'document_types' },
                    { key: 'status', label: 'Status', type: 'text' },
                ];
            case 'document_sector_response':
                return [
                    { key: 'document_type_code', label: 'Document type', type: 'select', lookup: 'document_types' },
                    { key: 'sector_code', label: 'Sector', type: 'select', lookup: 'sectors' },
                    { key: 'document_code', label: 'Document code', type: 'text' },
                    { key: 'question_code', label: 'Question code', type: 'text' },
                ];
            case 'event_impact':
                return [
                    { key: 'geography_code', label: 'Geography', type: 'select', lookup: 'geographies' },
                    { key: 'event_type', label: 'Event type', type: 'text' },
                    { key: 'metric_code', label: 'Metric code', type: 'text' },
                    { key: 'date_from', label: 'Date from', type: 'date' },
                    { key: 'date_to', label: 'Date to', type: 'date' },
                ];
            default:
                return [];
        }
    },
    activeColumns() {
        switch (this.dataset?.fact_table) {
            case 'country_year_sector_gas_values':
                return [
                    { key: 'country_code', label: 'Country' },
                    { key: 'year', label: 'Year' },
                    { key: 'sector_name', label: 'Sector' },
                    { key: 'gas_name', label: 'Gas' },
                    { key: 'value', label: 'Value' },
                ];
            case 'admin_period_indicator_values':
                return [
                    { key: 'geography_name', label: 'Geography' },
                    { key: 'indicator_name', label: 'Indicator' },
                    { key: 'period_date', label: 'Period date' },
                    { key: 'period_type', label: 'Type' },
                    { key: 'value', label: 'Value' },
                ];
            case 'country_year_indicator_values':
                return [
                    { key: 'country_code', label: 'Country' },
                    { key: 'indicator_name', label: 'Indicator' },
                    { key: 'year', label: 'Year' },
                    { key: 'value', label: 'Value' },
                ];
            case 'country_documents':
                return [
                    { key: 'document_type_name', label: 'Document type' },
                    { key: 'title', label: 'Title' },
                    { key: 'submission_date', label: 'Submission date' },
                    { key: 'status', label: 'Status' },
                    { key: 'summary', label: 'Summary' },
                ];
            case 'country_document_sector_responses':
                return [
                    { key: 'sector_name', label: 'Sector' },
                    { key: 'question_code', label: 'Question' },
                    { key: 'subsector_code', label: 'Subsector' },
                    { key: 'response_text', label: 'Response' },
                ];
            case 'event_impacts':
                return [
                    { key: 'geography_name', label: 'Geography' },
                    { key: 'event_date', label: 'Event date' },
                    { key: 'metric_code', label: 'Metric' },
                    { key: 'value', label: 'Value' },
                    { key: 'event_name', label: 'Event' },
                ];
            default:
                return [];
        }
    },
    chartPoints() {
        if (!this.records.length) {
            return [];
        }

        let points = [];

        switch (this.dataset?.fact_table) {
            case 'country_year_sector_gas_values':
            case 'country_year_indicator_values':
                points = this.records.map((record) => ({
                    label: String(record.year),
                    value: Number(record.value),
                }));
                break;
            case 'admin_period_indicator_values':
                points = this.records.map((record) => ({
                    label: `${record.indicator_code} · ${record.period_date}`,
                    value: Number(record.value),
                }));
                break;
            case 'event_impacts':
                points = this.records.map((record) => ({
                    label: `${record.geography_code} · ${record.metric_code}`,
                    value: Number(record.value),
                }));
                break;
            default:
                return [];
        }

        const max = Math.max(...points.map((point) => point.value), 1);

        return points.map((point) => ({
            ...point,
            width: Math.max((point.value / max) * 100, 4),
        }));
    },
    displayValue(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        if (typeof value === 'number') {
            return this.formatNumber(value);
        }

        if (typeof value === 'object') {
            return JSON.stringify(value);
        }

        return String(value);
    },
    formatNumber(value) {
        const number = Number(value);

        if (Number.isNaN(number)) {
            return value;
        }

        return new Intl.NumberFormat(undefined, {
            maximumFractionDigits: 2,
        }).format(number);
    },
    resetFilters() {
        this.filters = {
            country_code: '',
            geography_code: '',
            indicator_code: '',
            sector_code: '',
            gas_code: '',
            year: '',
            period_type: '',
            date_from: '',
            date_to: '',
            status: '',
            document_type_code: '',
            document_code: '',
            question_code: '',
            event_type: '',
            metric_code: '',
        };

        this.loadRecords();
    },
    goToPage(page) {
        if (page < 1 || page > this.pagination.last_page) {
            return;
        }

        this.loadRecords(page);
    },
    paginationLabel() {
        if (!this.pagination.total) {
            return 'No rows available.';
        }

        const start = ((this.pagination.current_page - 1) * this.pagination.per_page) + 1;
        const end = Math.min(this.pagination.current_page * this.pagination.per_page, this.pagination.total);

        return `${start}-${end} of ${this.pagination.total} rows`;
    },
}));

Alpine.data('emissionsComparison', (config = {}) => ({
    countries: config.countries ?? [],
    sectors: config.sectors ?? [],
    gases: config.gases ?? [],
    gasSectors: config.gas_sectors ?? {},
    years: config.years ?? [],
    records: config.records ?? [],
    recordValues: new Map(),
    recordCache: new Map(),
    recordsLoading: false,
    recordsError: '',
    requestId: 0,
    refreshTimer: null,
    ready: false,
    countryRankingCacheKey: '',
    countryRankingCache: [],
    countryMatrixSortKey: '',
    countryMatrixSortDirection: 'desc',
    trendTableSortKey: 'country',
    trendTableSortDirection: 'asc',
    trendTablePage: 1,
    trendTablePageSize: 20,
    selectedCountries: [],
    draftSelectedCountries: [],
    selectedSector: '',
    selectedSectorCountry: '',
    selectedGas: config.default_gas ?? '',
    selectedCompositionGases: [],
    yearStartIndex: 0,
    yearEndIndex: 0,
    viewMode: 'map',
    countryMenuOpen: false,
    countrySearch: '',
    compositionGasMenuOpen: false,
    highlightedCountry: '',
    selectedMapCountry: '',
    mapFeatures: [],
    mapMarkup: '',
    mapLoading: false,
    mapError: '',
    tooltip: { visible: false, x: 0, y: 0, label: '', year: '', value: 0 },
    chartDimensions: { width: 980, height: 380 },
    resizeObserver: null,
    viewModes: [
        { code: 'map', label: 'Map' },
        { code: 'trends', label: 'Country trends' },
        { code: 'countries', label: 'Country ranking' },
        { code: 'sectors', label: 'Sector ranking' },
        { code: 'composition', label: 'Gas composition' },
    ],
    colors: ['#045A58', '#E7B460', '#007D8A', '#A95D35', '#445B56', '#A23B3B', '#497C5B', '#6A5A7A'],
    init() {
        const requestedView = window.location.hash.slice(1);
        if (this.viewModes.some((mode) => mode.code === requestedView)) {
            this.viewMode = requestedView;
        }
        this.selectedCountries = this.countries.slice(0, 6).map((country) => country.code);
        this.draftSelectedCountries = [...this.selectedCountries];
        this.selectedSectorCountry = this.countries.some((country) => country.code === 'NGA') ? 'NGA' : this.countries[0]?.code ?? '';
        this.selectedMapCountry = this.selectedSectorCountry;
        this.selectedSector = this.defaultSector?.code ?? '';
        this.syncSelectedSectorToGas();
        if (this.viewMode === 'countries' && this.totalGhgGas) {
            this.selectedGas = this.totalGhgGas.code;
        }
        this.countryMatrixSortKey = this.countryMatrixPrimarySector?.code ?? this.sectors[0]?.code ?? 'country';
        this.selectedCompositionGases = this.componentGases.map((gas) => gas.code);
        ['selectedCountries', 'selectedSector', 'selectedSectorCountry', 'selectedGas', 'selectedCompositionGases', 'yearStartIndex', 'yearEndIndex', 'viewMode', 'selectedMapCountry'].forEach((property) => {
            this.$watch(property, () => {
                if (property !== 'selectedMapCountry') this.trendTablePage = 1;
                if (this.ready) this.scheduleRecordsRefresh();
            });
        });
        this.$watch('selectedGas', () => this.syncSelectedSectorToGas());
        window.addEventListener('hashchange', () => {
            const requestedView = window.location.hash.slice(1);
            if (this.viewModes.some((mode) => mode.code === requestedView) && requestedView !== this.viewMode) {
                this.setView(requestedView);
            }
        });
        if (!this.viewModes.some((mode) => mode.code === requestedView)) {
            window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${this.viewMode}`);
        }
        this.$nextTick(() => {
            this.yearEndIndex = Math.max(0, this.availableYears.length - 1);
            this.ready = true;
            this.refreshRecords();
            this.drawChart();
            this.resizeObserver = new ResizeObserver(() => this.drawChart());
            this.resizeObserver.observe(this.$refs.chart);
        });
        this.loadMap();
    },
    get availableYears() {
        return this.years;
    },
    syncSelectedSectorToGas() {
        const available = this.gasSectors[this.selectedGas] ?? [];
        if (!available.length || available.includes(this.selectedSector)) return;
        this.selectedSector = available.includes('total_excluding_lulucf')
            ? 'total_excluding_lulucf'
            : (this.sectors.find((sector) => available.includes(sector.code))?.code ?? available[0]);
    },
    get emptyChartMessage() {
        const gas = this.gases.find((item) => item.code === this.selectedGas)?.label ?? 'This gas';
        const sectors = (this.gasSectors[this.selectedGas] ?? [])
            .map((code) => this.sectors.find((sector) => sector.code === code)?.label ?? code);

        if (!sectors.length) return `No published records are available for ${gas}.`;

        return `No published records match these countries and years. ${gas} is available for: ${sectors.join(', ')}.`;
    },
    get selectedYearRange() {
        const start = Math.min(Number(this.yearStartIndex), Number(this.yearEndIndex));
        const end = Math.max(Number(this.yearStartIndex), Number(this.yearEndIndex));
        return this.availableYears.slice(start, end + 1);
    },
    get selectedYearLabel() {
        const years = this.selectedYearRange;
        return years.length ? `${years[0]} - ${years.at(-1)}` : 'No years available';
    },
    get chartYearLabel() {
        return ['map', 'trends', 'composition', 'sectors', 'countries'].includes(this.viewMode) ? this.selectedYearLabel : String(this.activeYear ?? 'No year available');
    },
    get visibleCountries() {
        const query = this.countrySearch.trim().toLowerCase();
        return this.countries.filter((country) => !query || country.label.toLowerCase().includes(query) || country.code.toLowerCase().includes(query)).slice(0, 100);
    },
    get countrySelectionLabel() {
        if (!this.draftSelectedCountries.length) return 'Choose countries';
        if (this.draftSelectedCountries.length === 1) return this.countryName(this.draftSelectedCountries[0]);
        return `${this.draftSelectedCountries.length} countries selected`;
    },
    get trendCountryLimitLabel() {
        return `${this.draftSelectedCountries.length} of ${this.countries.length} countries selected`;
    },
    get componentGases() {
        return this.gases.filter((gas) => !['all_ghg', 'kyotoghg'].includes(String(gas.code).toLowerCase()));
    },
    get compositionGasSelectionLabel() {
        if (this.hasTotalComposition) return this.totalGhgGas?.label ?? 'Total GHG';
        if (!this.selectedCompositionGases.length) return 'Choose gases';
        if (this.selectedCompositionGases.length === 1) return this.componentGases.find((gas) => gas.code === this.selectedCompositionGases[0])?.label ?? '1 gas selected';
        return `${this.selectedCompositionGases.length} gases selected`;
    },
    get totalGhgGas() {
        return this.gases.find((gas) => String(gas.code).toLowerCase() === 'kyotoghg')
            ?? this.gases.find((gas) => String(gas.code).toLowerCase() === 'all_ghg')
            ?? null;
    },
    get hasTotalComposition() {
        return Boolean(this.totalGhgGas && this.selectedCompositionGases.includes(this.totalGhgGas.code));
    },
    get compositionSeries() {
        const years = this.selectedYearRange;
        const gasCodes = this.hasTotalComposition && this.totalGhgGas ? [this.totalGhgGas.code] : this.selectedCompositionGases;
        return gasCodes.map((gasCode, index) => ({
            code: gasCode,
            label: this.gases.find((gas) => gas.code === gasCode)?.label ?? gasCode,
            color: this.colors[index % this.colors.length],
            values: years.map((year) => this.recordValue(this.selectedSectorCountry, this.selectedSector, gasCode, year)),
        })).filter((series) => series.values.some((value) => value !== 0));
    },
    get chartSeries() {
        const years = this.selectedYearRange;
        return this.selectedCountries.map((code, index) => ({
            code,
            label: this.countryName(code),
            color: this.colors[index % this.colors.length],
            values: years.map((year) => this.valueFor(code, year)),
        })).filter((series) => series.values.some((value) => value !== 0));
    },
    get sortedTrendSeries() {
        const direction = this.trendTableSortDirection === 'asc' ? 1 : -1;

        return [...this.chartSeries].sort((left, right) => {
            if (this.trendTableSortKey === 'country') {
                return direction * left.label.localeCompare(right.label);
            }

            const yearIndex = this.selectedYearRange.indexOf(Number(this.trendTableSortKey));
            return direction * ((left.values[yearIndex] ?? 0) - (right.values[yearIndex] ?? 0))
                || left.label.localeCompare(right.label);
        });
    },
    get paginatedTrendSeries() {
        const start = (this.trendTablePage - 1) * this.trendTablePageSize;
        return this.sortedTrendSeries.slice(start, start + this.trendTablePageSize);
    },
    get trendTablePageCount() {
        return Math.max(1, Math.ceil(this.sortedTrendSeries.length / this.trendTablePageSize));
    },
    get trendChartLimitExceeded() {
        return this.selectedCountries.length > 6;
    },
    get plottedTrendSeries() {
        return this.trendChartLimitExceeded ? [] : this.chartSeries;
    },
    get activeYear() {
        return this.selectedYearRange.at(-1) ?? null;
    },
    get countryRanking() {
        if (!this.activeYear) return [];
        const isCumulative = this.viewMode === 'map';
        const reportingPeriod = isCumulative ? this.selectedYearLabel : this.activeYear;
        const cacheKey = `${this.selectedGas}|${this.selectedSector}|${reportingPeriod}|${this.requestId}`;
        if (this.countryRankingCacheKey === cacheKey) return this.countryRankingCache;
        const ranking = this.countries.map((country, index) => ({
            label: country.label,
            code: country.code,
            color: this.colors[index % this.colors.length],
            value: isCumulative
                ? this.selectedYearRange.reduce((total, year) => total + this.valueFor(country.code, year), 0)
                : this.valueFor(country.code, this.activeYear),
            year: reportingPeriod,
        })).filter((row) => row.value !== 0).sort((left, right) => right.value - left.value);
        this.countryRankingCacheKey = cacheKey;
        this.countryRankingCache = ranking;
        return ranking;
    },
    get countryMatrixPrimarySector() {
        const primarySectorCodes = [
            'total_excluding_lulucf',
            'all_sectors',
            'all_sector',
            'total',
            'total_emissions',
            'national_total',
        ];

        return this.sectors.find((sector) => primarySectorCodes.includes(String(sector.code).toLowerCase())) ?? null;
    },
    get defaultSector() {
        return this.sectors.find((sector) => String(sector.code).toLowerCase() === 'agriculture')
            ?? this.sectors[0]
            ?? null;
    },
    get countryMatrixSectors() {
        const preferredOrder = [
            'total_excluding_lulucf',
            'agriculture',
            'waste',
            'energy',
            'industrial_processes_and_product_use',
            'other',
        ];

        return [...this.sectors].sort((left, right) => {
            const leftPosition = preferredOrder.indexOf(String(left.code).toLowerCase());
            const rightPosition = preferredOrder.indexOf(String(right.code).toLowerCase());
            const normalizedLeftPosition = leftPosition === -1 ? Number.MAX_SAFE_INTEGER : leftPosition;
            const normalizedRightPosition = rightPosition === -1 ? Number.MAX_SAFE_INTEGER : rightPosition;

            return normalizedLeftPosition - normalizedRightPosition || left.label.localeCompare(right.label);
        });
    },
    matrixRecordValue(country, sectorCode) {
        const values = this.selectedYearRange
            .map((year) => this.recordValues.get(this.recordKey(country, sectorCode, this.selectedGas, year)))
            .filter((value) => value !== undefined);

        return values.length ? values.reduce((total, value) => total + value, 0) : null;
    },
    get countryMatrixRows() {
        if (!this.activeYear) return [];

        const rows = this.countries.map((country) => {
            return {
                code: country.code,
                label: country.label,
                values: Object.fromEntries(this.countryMatrixSectors.map((sector) => [
                    sector.code,
                    this.matrixRecordValue(country.code, sector.code),
                ])),
            };
        });

        return rows.sort((left, right) => this.compareCountryMatrixRows(left, right));
    },
    countryMatrixValue(row, key) {
        if (key === 'country') return row.label;

        return row.values[key] ?? null;
    },
    compareCountryMatrixRows(left, right) {
        const leftValue = this.countryMatrixValue(left, this.countryMatrixSortKey);
        const rightValue = this.countryMatrixValue(right, this.countryMatrixSortKey);
        const direction = this.countryMatrixSortDirection === 'asc' ? 1 : -1;

        if (leftValue === null && rightValue === null) return left.label.localeCompare(right.label);
        if (leftValue === null) return 1;
        if (rightValue === null) return -1;
        if (typeof leftValue === 'string') return direction * leftValue.localeCompare(rightValue);

        return direction * (leftValue - rightValue) || left.label.localeCompare(right.label);
    },
    sortCountryMatrix(key) {
        if (this.countryMatrixSortKey === key) {
            this.countryMatrixSortDirection = this.countryMatrixSortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        this.countryMatrixSortKey = key;
        this.countryMatrixSortDirection = key === 'country' ? 'asc' : 'desc';
    },
    sortTrendTable(key) {
        this.trendTablePage = 1;
        const sortKey = String(key);
        if (this.trendTableSortKey === sortKey) {
            this.trendTableSortDirection = this.trendTableSortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        this.trendTableSortKey = sortKey;
        this.trendTableSortDirection = sortKey === 'country' ? 'asc' : 'desc';
    },
    trendTableSortIcon(key) {
        if (this.trendTableSortKey !== String(key)) return 'unfold_more';

        return this.trendTableSortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward';
    },
    trendTableSortState(key) {
        if (this.trendTableSortKey !== String(key)) return 'none';

        return this.trendTableSortDirection === 'asc' ? 'ascending' : 'descending';
    },
    countryMatrixSortIcon(key) {
        if (this.countryMatrixSortKey !== key) return 'unfold_more';

        return this.countryMatrixSortDirection === 'asc' ? 'arrow_upward' : 'arrow_downward';
    },
    countryMatrixSortState(key) {
        if (this.countryMatrixSortKey !== key) return 'none';

        return this.countryMatrixSortDirection === 'asc' ? 'ascending' : 'descending';
    },
    get sectorRanking() {
        const country = this.selectedSectorCountry;
        if (!country || !this.activeYear) return [];
        return this.sectors.map((sector, index) => ({
            label: sector.label,
            code: sector.code,
            color: this.colors[index % this.colors.length],
            value: this.selectedYearRange.reduce((total, year) => {
                const value = this.recordValues.get(this.recordKey(country, sector.code, this.selectedGas, year));

                return value === undefined ? total : total + value;
            }, 0),
            hasData: this.selectedYearRange.some((year) => this.recordValues.has(this.recordKey(country, sector.code, this.selectedGas, year))),
            year: this.selectedYearLabel,
        })).map((row) => ({ ...row, value: row.hasData ? row.value : null }))
            .sort((left, right) => {
                if (left.value === null && right.value === null) return left.label.localeCompare(right.label);
                if (left.value === null) return 1;
                if (right.value === null) return -1;

                return right.value - left.value;
            });
    },
    get tableRows() {
        if (this.viewMode === 'composition') return this.compositionSeries.map((series) => ({
            label: series.label,
            value: series.values.at(-1) ?? 0,
            year: this.selectedYearLabel,
        })).filter((row) => row.value !== 0).sort((left, right) => right.value - left.value);
        if (this.viewMode === 'sectors') return this.sectorRanking;
        if (this.viewMode === 'countries') return this.countryMatrixRows;
        if (this.viewMode === 'map') return this.countryRanking.slice(0, 20);
        return this.countryRanking.filter((row) => this.selectedCountries.includes(row.code));
    },
    get tableRowCount() {
        return this.viewMode === 'trends' ? this.chartSeries.length : this.tableRows.length;
    },
    get tableTitle() {
        if (this.viewMode === 'composition') return 'Selected gas components';
        if (this.viewMode === 'trends') return 'Country emissions by selected years';
        if (this.viewMode === 'map') return 'Countries ranked for the selected reporting period';
        return this.viewMode === 'sectors' ? 'Sectors ranked by cumulative emissions' : 'Latest country totals';
    },
    get tableLabel() {
        if (this.viewMode === 'composition') return 'Gas';
        return this.viewMode === 'sectors' ? 'Sector' : 'Country';
    },
    get tableSubtitle() {
        if (this.viewMode === 'composition') return 'End-of-range values for each selected gas.';
        if (this.viewMode === 'trends') return 'Compare every selected reporting year for each country.';
        if (this.viewMode === 'sectors') return 'Each value is the cumulative published emissions for the selected gas and reporting-year range.';
        if (this.viewMode === 'map') return 'Top 20 countries by cumulative value for the selected gas, sector, and reporting-year range.';
        return 'Ranked values use the selected gas, sector, and reporting year.';
    },
    get chartTitle() {
        if (this.viewMode === 'map') return 'Global emissions map';
        if (this.viewMode === 'composition') return `${this.countryName(this.selectedSectorCountry)} ${this.sectorName(this.selectedSector)} gas composition`;
        if (this.viewMode === 'countries') return 'Cumulative country emissions matrix';
        if (this.viewMode === 'sectors') return `${this.countryName(this.selectedSectorCountry)} cumulative sector emissions`;
        return 'Country emissions over time';
    },
    get chartIcon() {
        if (this.viewMode === 'map') return 'public';
        if (this.viewMode === 'composition') return 'co2';
        if (this.viewMode === 'countries') return 'table_chart';
        if (this.viewMode === 'sectors') return 'factory';
        return 'public';
    },
    get chartSubtitle() {
        if (this.viewMode === 'map') return 'Select a country to inspect its cumulative published value, rank, source, and focused trend.';
        if (this.viewMode === 'composition') return this.hasTotalComposition ? 'Source-provided Total GHG for the selected country and sector.' : 'Selected gas components are stacked for the selected country and sector.';
        if (this.viewMode === 'sectors') return 'Every published sector is ordered by its cumulative value across the selected reporting-year range.';
        if (this.viewMode === 'countries') return 'Compare each country across every published sector for the selected reporting-year range.';
        return 'Each line is one selected country; use the country control to compare a focused set.';
    },
    get filterNote() {
        if (this.viewMode === 'composition') return this.hasTotalComposition
            ? 'Total GHG is a source-provided aggregate. It is shown separately and is never added to component gases.'
            : 'Selected component gases are stacked only when they share the source unit and accounting boundary. Total GHG is excluded to prevent double counting.';
        const gas = this.gases.find((item) => item.code === this.selectedGas);
        const gasLabel = gas?.label ?? 'selected gas';
        if (this.viewMode === 'countries') return `${gasLabel} is selected by default. Each cell is cumulative across the selected reporting years for one country, sector, and gas; sectors are never added together.`;
        if (this.viewMode === 'map') return `${gasLabel} is cumulative across the selected reporting years for the same country and sector. Values are never calculated by adding gas types together.`;
        if (this.viewMode === 'sectors') return `${gasLabel} is shown for every published sector. Values are summed only across the selected reporting years for the same country, sector, and gas.`;
        if (!this.hasIndividualGases) return `${gasLabel} is the only gas series in the active dataset version. Individual gases will appear after approved PRIMAP gas rows are published.`;
        return `${gasLabel} is filtered as a single published gas series. Values are never calculated by adding different gas types together.`;
    },
    get hasIndividualGases() {
        return this.gases.some((gas) => !['all_ghg', 'kyotoghg'].includes(String(gas.code).toLowerCase()));
    },
    get hasChartData() {
        if (this.viewMode === 'map') return this.mapRows.length > 0;
        if (this.viewMode === 'composition') return this.compositionSeries.length > 0;
        if (this.viewMode === 'countries') return this.countryMatrixRows.some((row) => Object.values(row.values).some((value) => value !== null));
        return this.viewMode === 'trends'
            ? !this.trendChartLimitExceeded && this.chartSeries.length > 0
            : this.tableRows.length > 0;
    },
    get profileCountry() {
        if (this.viewMode === 'map') return this.selectedMapCountry;
        if (this.viewMode === 'sectors') return this.selectedSectorCountry;
        return this.selectedCountries.length === 1 ? this.selectedCountries[0] : null;
    },
    get profileCountryLabel() {
        return this.countryName(this.profileCountry);
    },
    get profile() {
        const country = this.profileCountry;
        if (!country || !this.activeYear) return null;
        const mapRow = this.viewMode === 'map' ? this.mapRowFor(country) : null;
        const sectors = this.sectors.map((sector) => ({
            label: sector.label,
            value: this.recordValue(country, sector.code, this.selectedGas, this.activeYear),
        })).sort((left, right) => right.value - left.value);
        return {
            total: mapRow?.value ?? this.valueFor(country, this.activeYear),
            largestSector: sectors[0]?.label,
            largestValue: sectors[0]?.value,
            year: mapRow?.year ?? this.activeYear,
        };
    },
    get profilePeriodLabel() {
        return this.viewMode === 'map' ? 'reporting period' : 'reporting year';
    },
    countryName(code) {
        return this.countries.find((country) => country.code === code)?.label ?? code ?? 'Selected country';
    },
    sectorName(code) {
        return this.sectors.find((sector) => sector.code === code)?.label ?? 'selected sector';
    },
    setView(mode) {
        if (!this.viewModes.some((item) => item.code === mode)) return;
        this.viewMode = mode;
        if (mode === 'trends') this.draftSelectedCountries = [...this.selectedCountries];
        if (window.location.hash !== `#${mode}`) {
            window.location.hash = mode;
        }
        if (mode === 'countries') {
            if (this.totalGhgGas) this.selectedGas = this.totalGhgGas.code;
        }
        if (['map', 'trends', 'composition'].includes(mode) && !this.selectedSector) {
            this.selectedSector = this.defaultSector?.code ?? '';
        }
    },
    selectAllCountries() {
        this.draftSelectedCountries = this.countries.map((country) => country.code);
    },
    applyCountries() {
        this.selectedCountries = [...this.draftSelectedCountries];
        this.trendTablePage = 1;
        this.countryMenuOpen = false;
    },
    selectAllCompositionGases() {
        this.selectedCompositionGases = this.componentGases.map((gas) => gas.code);
    },
    normalizeCompositionGasSelection(changedGasCode) {
        const totalCode = this.totalGhgGas?.code;
        if (!totalCode) return;
        if (changedGasCode === totalCode && this.selectedCompositionGases.includes(totalCode)) {
            this.selectedCompositionGases = [totalCode];
            return;
        }
        this.selectedCompositionGases = this.selectedCompositionGases.filter((gasCode) => gasCode !== totalCode);
    },
    toggleCountry(code) {
        this.highlightedCountry = this.highlightedCountry === code ? '' : code;
        this.$nextTick(() => this.drawChart());
    },
    get mapRows() {
        return this.countryRanking.map((row, index) => ({ ...row, rank: index + 1 }));
    },
    get selectedMapRow() {
        return this.mapRows.find((row) => row.code === this.selectedMapCountry) ?? null;
    },
    get mapMinimum() {
        return Math.min(...this.mapRows.map((row) => row.value));
    },
    get mapMaximum() {
        return Math.max(...this.mapRows.map((row) => row.value));
    },
    async loadMap() {
        this.mapLoading = true;
        this.mapError = '';
        try {
            const response = await fetch('/maps/world-countries.geojson', {
                headers: { Accept: 'application/geo+json, application/json' },
            });
            if (!response.ok) throw new Error(`Map request failed with status ${response.status}`);
            const world = await response.json();
            this.mapFeatures = world.features ?? [];
            this.rebuildMapMarkup();
        } catch (error) {
            this.mapError = 'The global map could not be loaded.';
        } finally {
            this.mapLoading = false;
        }
    },
    mapRowFor(code) {
        return this.mapRows.find((row) => row.code === code) ?? null;
    },
    mapColorFor(code) {
        const row = this.mapRowFor(code);
        if (!row) return '#D9E2DF';
        const minimum = this.mapMinimum;
        const maximum = this.mapMaximum;
        const intensity = maximum === minimum ? 1 : (Math.log(row.value) - Math.log(minimum)) / (Math.log(maximum) - Math.log(minimum));
        const red = Math.round(168 - (164 * intensity));
        const green = Math.round(212 - (122 * intensity));
        const blue = Math.round(205 - (117 * intensity));
        return `rgb(${red} ${green} ${blue})`;
    },
    mapLabel(feature) {
        const code = String(feature.properties?.code ?? '');
        const row = this.mapRowFor(code);
        return row ? `${row.label}: ${this.formatNumber(row.value)}, rank ${row.rank}` : `${feature.properties?.name ?? 'Country'}: no published value`;
    },
    rebuildMapMarkup() {
        const escape = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
        this.mapMarkup = this.mapFeatures.map((feature) => {
            const code = String(feature.properties?.code ?? '');
            const row = this.mapRowFor(code);
            const path = mapPath(feature.geometry, (longitude, latitude) => [
                ((longitude + 180) / 360) * 1000,
                ((90 - latitude) / 180) * 500,
            ]);
            const selected = this.selectedMapCountry === code;
            return `<path d="${path}" fill="${this.mapColorFor(code)}" stroke="${selected ? '#E7B460' : '#ffffff'}" stroke-width="${selected ? '2.5' : '0.55'}"${row ? ` data-country-code="${escape(code)}" class="cursor-pointer"` : ''}><title>${escape(this.mapLabel(feature))}</title></path>`;
        }).join('');
    },
    selectMapCountry(code) {
        if (!this.mapRowFor(code)) return;
        this.selectedMapCountry = code;
        this.selectedSectorCountry = code;
        this.highlightedCountry = code;
    },
    selectMapCountryFromEvent(event) {
        const path = event.target.closest?.('[data-country-code]');
        this.selectMapCountry(path?.dataset.countryCode);
    },
    openCountryTrend() {
        if (!this.selectedMapCountry) return;
        this.selectedCountries = [this.selectedMapCountry];
        this.setView('trends');
    },
    openMatrixCountryTrend(code) {
        this.selectedCountries = [code];
        this.selectedSector = this.countryMatrixPrimarySector?.code ?? this.sectors[0]?.code ?? '';
        this.setView('trends');
    },
    requestYears() {
        if (['map', 'trends', 'composition', 'sectors', 'countries'].includes(this.viewMode)) {
            return { from: this.selectedYearRange[0], to: this.selectedYearRange.at(-1) };
        }

        return { from: this.activeYear, to: this.activeYear };
    },
    scheduleRecordsRefresh() {
        window.clearTimeout(this.refreshTimer);
        this.refreshTimer = window.setTimeout(() => this.refreshRecords(), 0);
    },
    refreshRecords() {
        const years = this.requestYears();
        if (!this.selectedGas || !years.from || !years.to) return;

        const params = new URLSearchParams({
            gas: this.selectedGas,
            year_from: String(years.from),
            year_to: String(years.to),
        });
        if (this.viewMode === 'trends') {
            if (this.selectedCountries.length < this.countries.length) {
                params.set('countries', this.selectedCountries.join(','));
            }
        } else if (this.viewMode === 'sectors') {
            params.set('countries', this.selectedSectorCountry);
        } else if (this.viewMode === 'composition') {
            params.set('countries', this.selectedSectorCountry);
            params.set('sector', this.selectedSector);
            params.set('gases', this.selectedCompositionGases.join(','));
        } else if (this.viewMode !== 'countries' && this.selectedSector) {
            params.set('sector', this.selectedSector);
        }
        const cacheKey = params.toString();
        const requestId = ++this.requestId;
        this.recordsError = '';

        if (this.recordCache.has(cacheKey)) {
            this.setRecords(this.recordCache.get(cacheKey), requestId);
            return;
        }

        this.recordsLoading = true;
        fetch(`/api/emissions/dashboard?${cacheKey}`, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error(`Emissions request failed with status ${response.status}`);
                return response.json();
            })
            .then((payload) => {
                const records = payload.records ?? [];
                this.recordCache.set(cacheKey, records);
                this.setRecords(records, requestId);
            })
            .catch(() => {
                if (requestId !== this.requestId) return;
                this.records = [];
                this.recordValues = new Map();
                this.recordsError = 'Published emissions could not be loaded. Please try again.';
                this.drawChart();
                this.rebuildMapMarkup();
            })
            .finally(() => {
                if (requestId === this.requestId) this.recordsLoading = false;
            });
    },
    setRecords(records, requestId) {
        if (requestId !== this.requestId) return;
        this.records = records;
        this.recordValues = new Map(records.map((record) => [this.recordKey(record.country, record.sector, record.gas, record.year), Number(record.value)]));
        this.countryRankingCacheKey = '';
        this.$nextTick(() => {
            this.drawChart();
            this.rebuildMapMarkup();
        });
    },
    recordKey(country, sector, gas, year) {
        return `${country}|${sector}|${gas}|${year}`;
    },
    recordValue(country, sector, gas, year) {
        return this.recordValues.get(this.recordKey(country, sector, gas, year)) ?? 0;
    },
    valueFor(country, year) {
        if (this.selectedSector) return this.recordValue(country, this.selectedSector, this.selectedGas, year);
        return this.countryMatrixPrimarySector
            ? this.recordValue(country, this.countryMatrixPrimarySector.code, this.selectedGas, year)
            : 0;
    },
    formatNumber(value) {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 }).format(value);
    },
    drawChart() {
        const canvas = this.$refs.chart;
        if (!canvas) return;
        const bounds = canvas.getBoundingClientRect();
        const width = Math.max(1, Math.round(bounds.width || 980));
        const height = Math.max(1, Math.round(bounds.height || 380));
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        const pixelWidth = Math.round(width * pixelRatio);
        const pixelHeight = Math.round(height * pixelRatio);

        if (canvas.width !== pixelWidth || canvas.height !== pixelHeight) {
            canvas.width = pixelWidth;
            canvas.height = pixelHeight;
        }

        const context = canvas.getContext('2d');
        context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        context.clearRect(0, 0, width, height);
        this.chartDimensions = { width, height };
        if (['map', 'countries'].includes(this.viewMode)) return;
        if (this.viewMode === 'trends') {
            if (!this.trendChartLimitExceeded) this.drawTrendChart(context, width, height);
        } else if (this.viewMode === 'composition') this.drawStackedAreaChart(context, width, height);
        else this.drawBarChart(context, width, height, this.tableRows);
    },
    drawGrid(context, chart, maximum) {
        context.strokeStyle = '#d9e2df';
        context.lineWidth = 1;
        context.font = '11px Inter, sans-serif';
        context.fillStyle = '#4d5753';
        context.textAlign = 'right';
        for (let step = 0; step <= 4; step += 1) {
            const y = chart.bottom - ((step / 4) * (chart.bottom - chart.top));
            context.beginPath(); context.moveTo(chart.left, y); context.lineTo(chart.right, y); context.stroke();
            context.fillText(this.formatNumber((maximum * step) / 4), chart.left - 9, y + 4);
        }
        context.strokeStyle = '#9ca8a3';
        context.beginPath(); context.moveTo(chart.left, chart.top); context.lineTo(chart.left, chart.bottom); context.lineTo(chart.right, chart.bottom); context.stroke();
    },
    drawTrendChart(context, width, height) {
        const chart = { left: 72, right: width - 30, top: 24, bottom: height - 52 };
        const years = this.selectedYearRange;
        const series = this.chartSeries;
        if (!years.length || !series.length) return this.drawEmpty(context, width, height);
        const maximum = Math.max(1, ...series.flatMap((item) => item.values));
        this.drawGrid(context, chart, maximum);
        const finalIndex = Math.max(1, years.length - 1);
        context.strokeStyle = '#e7ecea';
        context.lineWidth = 1;
        years.forEach((year, index) => {
            if (years.length > 10 && index !== 0 && index !== years.length - 1 && index % Math.ceil(years.length / 8) !== 0) return;
            const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
            context.beginPath(); context.moveTo(x, chart.top); context.lineTo(x, chart.bottom); context.stroke();
        });
        series.forEach((item) => {
            context.strokeStyle = item.color; context.lineWidth = 3; context.lineJoin = 'round'; context.lineCap = 'round'; context.beginPath();
            item.values.forEach((value, index) => {
                const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
                const y = chart.bottom - ((value / maximum) * (chart.bottom - chart.top));
                if (index === 0) context.moveTo(x, y); else context.lineTo(x, y);
            });
            context.stroke();
        });
        const highlighted = series.find((item) => item.code === this.highlightedCountry);
        if (highlighted) {
            context.font = '10px Inter, sans-serif';
            highlighted.values.forEach((value, index) => {
                const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
                const y = chart.bottom - ((value / maximum) * (chart.bottom - chart.top));
                const label = this.formatNumber(value);
                const labelWidth = context.measureText(label).width + 10;
                const labelX = Math.max(chart.left, Math.min(chart.right - labelWidth, x - (labelWidth / 2)));
                const labelY = Math.max(chart.top + 15, y - 10);

                context.fillStyle = '#ffffff';
                context.strokeStyle = highlighted.color;
                context.lineWidth = 1;
                context.fillRect(labelX, labelY - 12, labelWidth, 15);
                context.strokeRect(labelX, labelY - 12, labelWidth, 15);
                context.fillStyle = highlighted.color;
                context.beginPath(); context.arc(x, y, 4, 0, Math.PI * 2); context.fill();
                context.fillStyle = '#25302b';
                context.textAlign = 'center';
                context.fillText(label, labelX + (labelWidth / 2), labelY - 1);
            });
        }
        context.fillStyle = '#4d5753'; context.font = '11px Inter, sans-serif'; context.textAlign = 'center';
        years.forEach((year, index) => {
            const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
            if (years.length <= 10 || index === 0 || index === years.length - 1 || index % Math.ceil(years.length / 8) === 0) context.fillText(year, x, height - 22);
        });
    },
    drawStackedAreaChart(context, width, height) {
        const chart = { left: 72, right: width - 30, top: 24, bottom: height - 52 };
        const years = this.selectedYearRange;
        const series = this.compositionSeries;
        if (!years.length || !series.length) return this.drawEmpty(context, width, height);
        const totals = years.map((_, index) => series.reduce((sum, item) => sum + item.values[index], 0));
        const maximum = Math.max(1, ...totals);
        const finalIndex = Math.max(1, years.length - 1);
        this.drawGrid(context, chart, maximum);
        context.strokeStyle = '#e7ecea';
        context.lineWidth = 1;
        years.forEach((year, index) => {
            if (years.length > 10 && index !== 0 && index !== years.length - 1 && index % Math.ceil(years.length / 8) !== 0) return;
            const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
            context.beginPath(); context.moveTo(x, chart.top); context.lineTo(x, chart.bottom); context.stroke();
        });
        let lower = Array(years.length).fill(0);
        series.forEach((item) => {
            const upper = item.values.map((value, index) => lower[index] + value);
            context.beginPath();
            upper.forEach((value, index) => {
                const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
                const y = chart.bottom - ((value / maximum) * (chart.bottom - chart.top));
                if (index === 0) context.moveTo(x, y); else context.lineTo(x, y);
            });
            lower.slice().reverse().forEach((value, reverseIndex) => {
                const index = years.length - 1 - reverseIndex;
                const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
                const y = chart.bottom - ((value / maximum) * (chart.bottom - chart.top));
                context.lineTo(x, y);
            });
            context.closePath();
            context.fillStyle = `${item.color}b8`;
            context.fill();
            context.strokeStyle = item.color;
            context.lineWidth = 1.5;
            context.stroke();
            lower = upper;
        });
        context.fillStyle = '#4d5753'; context.font = '11px Inter, sans-serif'; context.textAlign = 'center';
        years.forEach((year, index) => {
            const x = chart.left + ((index / finalIndex) * (chart.right - chart.left));
            if (years.length <= 10 || index === 0 || index === years.length - 1 || index % Math.ceil(years.length / 8) === 0) context.fillText(year, x, height - 22);
        });
    },
    drawBarChart(context, width, height, rows) {
        if (!rows.length) return this.drawEmpty(context, width, height);
        const shown = rows.slice(0, 15);
        const chart = { left: 190, right: width - 68, top: 28, bottom: height - 30 };
        const maximum = Math.max(1, ...shown.map((row) => row.value ?? 0));
        const barHeight = Math.max(14, Math.min(25, (chart.bottom - chart.top) / shown.length - 8));
        context.strokeStyle = '#d9e2df';
        context.lineWidth = 1;
        context.font = '11px Inter, sans-serif';
        context.fillStyle = '#4d5753';
        context.textAlign = 'center';
        for (let step = 0; step <= 4; step += 1) {
            const x = chart.left + ((step / 4) * (chart.right - chart.left));
            context.beginPath(); context.moveTo(x, chart.top); context.lineTo(x, chart.bottom); context.stroke();
            context.fillText(this.formatNumber((maximum * step) / 4), x, chart.bottom + 18);
        }
        context.strokeStyle = '#9ca8a3';
        context.beginPath(); context.moveTo(chart.left, chart.top); context.lineTo(chart.left, chart.bottom); context.lineTo(chart.right, chart.bottom); context.stroke();
        context.font = '12px Inter, sans-serif';
        shown.forEach((row, index) => {
            const y = chart.top + (index * ((chart.bottom - chart.top) / shown.length)) + 4;
            const length = ((row.value ?? 0) / maximum) * (chart.right - chart.left);
            context.fillStyle = '#e4ebe8'; context.fillRect(chart.left, y, chart.right - chart.left, barHeight);
            context.fillStyle = row.color; context.fillRect(chart.left, y, length, barHeight);
            context.fillStyle = '#25302b'; context.textAlign = 'right'; context.fillText(row.label, chart.left - 12, y + barHeight - 4);
            if (row.value !== null) {
                context.textAlign = 'left'; context.fillText(this.formatNumber(row.value), Math.min(chart.right + 8, chart.left + length + 8), y + barHeight - 4);
            }
        });
    },
    handleChartHover(event) {
        const canvas = this.$refs.chart;
        const rect = canvas.getBoundingClientRect();
        const x = (event.clientX - rect.left) * (this.chartDimensions.width / rect.width);
        const y = (event.clientY - rect.top) * (this.chartDimensions.height / rect.height);
        const point = this.viewMode === 'trends' ? this.nearestTrendPoint(x, y, this.chartDimensions.width, this.chartDimensions.height) : this.viewMode === 'composition' ? this.compositionPoint(x, this.chartDimensions.width, this.chartDimensions.height) : this.barPoint(y, this.chartDimensions.width, this.chartDimensions.height);
        if (!point) return this.hideTooltip();
        this.tooltip = { visible: true, x: event.clientX - rect.left, y: event.clientY - rect.top, ...point };
    },
    hideTooltip() {
        this.tooltip.visible = false;
    },
    nearestTrendPoint(x, y, width, height) {
        const years = this.selectedYearRange;
        const series = this.chartSeries;
        if (!years.length || !series.length) return null;
        const chart = { left: 72, right: width - 30, top: 24, bottom: height - 52 };
        const maximum = Math.max(1, ...series.flatMap((item) => item.values));
        const finalIndex = Math.max(1, years.length - 1);
        let nearest = null;
        series.forEach((item) => item.values.forEach((value, index) => {
            const pointX = chart.left + ((index / finalIndex) * (chart.right - chart.left));
            const pointY = chart.bottom - ((value / maximum) * (chart.bottom - chart.top));
            const distance = Math.hypot(x - pointX, y - pointY);
            if (!nearest || distance < nearest.distance) nearest = { distance, label: item.label, year: years[index], value };
        }));
        return nearest?.distance < 34 ? nearest : null;
    },
    compositionPoint(x, width, height) {
        const years = this.selectedYearRange;
        const series = this.compositionSeries;
        if (!years.length || !series.length) return null;
        const chart = { left: 72, right: width - 30 };
        const finalIndex = Math.max(1, years.length - 1);
        const index = Math.max(0, Math.min(years.length - 1, Math.round(((x - chart.left) / (chart.right - chart.left)) * finalIndex)));
        const pointX = chart.left + ((index / finalIndex) * (chart.right - chart.left));
        if (Math.abs(x - pointX) > 28) return null;
        return {
            label: this.hasTotalComposition ? 'Total GHG' : 'Selected gas components',
            year: years[index],
            value: series.reduce((sum, item) => sum + item.values[index], 0),
        };
    },
    barPoint(y, width, height) {
        const shown = this.tableRows.slice(0, 15);
        if (!shown.length) return null;
        const chart = { top: 28, bottom: height - 30 };
        const barHeight = Math.max(14, Math.min(25, (chart.bottom - chart.top) / shown.length - 8));
        const rowHeight = (chart.bottom - chart.top) / shown.length;
        const index = Math.floor((y - chart.top) / rowHeight);
        if (index < 0 || index >= shown.length) return null;
        const barY = chart.top + (index * rowHeight) + 4;
        return y >= barY && y <= barY + barHeight ? shown[index] : null;
    },
    drawEmpty(context, width, height) {
        context.fillStyle = '#4d5753'; context.font = '14px Inter, sans-serif'; context.textAlign = 'center';
        context.fillText('No published records match these controls.', width / 2, height / 2);
    },
}));

window.Alpine = Alpine;
window.gsap = gsap;

Alpine.start();

if (prefersReducedMotion.matches) {
    document.documentElement.dataset.motion = 'reduced';
} else {
    AOS.init({
        duration: 650,
        easing: 'ease-out-cubic',
        once: true,
        offset: 80,
    });

    gsap.registerPlugin(ScrollTrigger);
}
