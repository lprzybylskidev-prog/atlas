import type { AtlasBarChartData, AtlasChartPoint } from '../Types/charts';

export interface NormalizedBarChartPoint extends AtlasChartPoint {
    ratio: number;
}

export interface NormalizedBarChartSeries {
    label: string;
    points: NormalizedBarChartPoint[];
}

export interface NormalizedBarChartData {
    title: string;
    description: string | null;
    unit: string | null;
    maxValue: number;
    series: NormalizedBarChartSeries[];
}

export function normalizeBarChartData(chart: AtlasBarChartData): NormalizedBarChartData {
    const values = chart.series.flatMap((series) =>
        series.points.map((point) => Math.max(0, Number.isFinite(point.value) ? point.value : 0)),
    );
    const maxValue = Math.max(1, ...values);

    return {
        title: chart.title,
        description: chart.description ?? null,
        unit: chart.unit ?? null,
        maxValue,
        series: chart.series.map((series) => ({
            label: series.label,
            points: series.points.map((point) => {
                const value = Math.max(0, Number.isFinite(point.value) ? point.value : 0);

                return {
                    label: point.label,
                    value,
                    ratio: value / maxValue,
                };
            }),
        })),
    };
}
