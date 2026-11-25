// Okabe-Ito colours, darkened where needed to reach 3:1 contrast against white (WCAG 1.4.11).
// Each series also differs by line style or point shape, so colour is never the only cue.
export const colours = {
    blue: '#0072b2',
    vermillion: '#c4501a',
    green: '#00785a',
    purple: '#a2537f',
    orange: '#b46f00',
    ink: '#1f2933',
    grid: '#d9dee3',
    muted: '#52606d',
};

export const series = [colours.blue, colours.vermillion, colours.green, colours.purple, colours.orange];

// Sequential blues for the map (ColorBrewer "Blues", colour-blind safe).
// The text colour on each step keeps at least 4.5:1 contrast.
export const mapScale = [
    { fill: '#eff3ff', text: '#1f2933' },
    { fill: '#c6dbef', text: '#1f2933' },
    { fill: '#9ecae1', text: '#1f2933' },
    { fill: '#4292c6', text: '#0b0f14' },
    { fill: '#08519c', text: '#ffffff' },
];
