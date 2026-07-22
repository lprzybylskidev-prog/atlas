export interface AtlasChartPoint {
    label: string;
    value: number;
}

export interface AtlasBarChartSeries {
    label: string;
    points: AtlasChartPoint[];
}

export interface AtlasBarChartData {
    title: string;
    description?: string | null;
    unit?: string | null;
    series: AtlasBarChartSeries[];
}
