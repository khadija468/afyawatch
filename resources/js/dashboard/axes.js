/** Value axis that always starts at zero, so changes are never exaggerated. */
export const zeroAxis = (title, extra = {}) => ({
    beginAtZero: true,
    min: 0,
    title: { display: Boolean(title), text: title },
    ticks: { precision: 0 },
    ...extra,
});
