import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { colours } from './palette';

// Register only what the dashboard uses, which keeps the bundle small.
Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--font-sans') || 'system-ui, sans-serif';
Chart.defaults.font.size = 13;
Chart.defaults.color = colours.ink;
Chart.defaults.borderColor = colours.grid;
Chart.defaults.animation = false; // faster on slow phones, and kinder to people sensitive to motion
Chart.defaults.maintainAspectRatio = false;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxHeight = 8;
Chart.defaults.plugins.tooltip.padding = 10;

export { Chart };
