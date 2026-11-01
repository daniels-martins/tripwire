<script src="https://cdn.jsdelivr.net/npm/apexcharts@4.5.0/dist/apexcharts.min.js"></script>
<script>
// Reads a viz.css token off :root so charts follow the palette and the theme.
window.vizToken = (name) =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim();

// Shared ApexCharts chrome: our ink, grid and type scale, in whichever theme is live.
window.vizBase = () => ({
    chart: {
        fontFamily: 'inherit',
        foreColor: vizToken('--color-viz-ink-muted'),
        toolbar: { show: false },
        animations: { enabled: true, speed: 200, animateGradually: { enabled: false } },
        background: 'transparent',
    },
    grid: { borderColor: vizToken('--color-viz-grid'), strokeDashArray: 0 },
    tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
    legend: { labels: { colors: vizToken('--color-viz-ink-soft') }, markers: { strokeWidth: 0 } },
    dataLabels: { enabled: false },
    stroke: { width: 2, curve: 'straight' },
});

// Re-render on theme flip: the CSS vars change, ApexCharts has already read them.
window.vizWatchTheme = (apply) => {
    new MutationObserver(() => apply()).observe(document.documentElement, {
        attributes: true, attributeFilter: ['class'],
    });
};
</script>
