import { describe, expect, it } from 'vitest';

import atlasBarChart from '../Components/AtlasBarChart.vue?raw';
import { normalizeBarChartData } from './chartData';

describe('chart data helpers', () => {
    it('normalizes bar chart values without allowing invalid or negative ratios', () => {
        const chart = normalizeBarChartData({
            title: 'Cases by status',
            unit: 'cases',
            series: [
                {
                    label: 'Cases',
                    points: [
                        { label: 'Open', value: 12 },
                        { label: 'Closed', value: -4 },
                        { label: 'Unknown', value: Number.NaN },
                    ],
                },
            ],
        });

        expect(chart.maxValue).toBe(12);
        expect(chart.series[0]?.points.map((point) => point.ratio)).toEqual([1, 0, 0]);
    });

    it('keeps the shared bar chart accessible and themed for light and dark modes', () => {
        expect(atlasBarChart).toContain('role="group"');
        expect(atlasBarChart).toContain(':aria-labelledby="chartTitleId"');
        expect(atlasBarChart).toContain('dark:text-zinc-50');
        expect(atlasBarChart).toContain('dark:fill-zinc-800');
        expect(atlasBarChart).toContain('dark:fill-teal-400');
        expect(atlasBarChart).not.toMatch(/\stitle=/);
    });
});
