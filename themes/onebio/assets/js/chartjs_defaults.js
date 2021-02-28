/* Default chart settings */
Chart.defaults.global.elements.line.borderWidth = 4;
Chart.defaults.global.elements.point.radius = 3;
Chart.defaults.global.elements.point.borderWidth = 6;

/* Default chart options */
let chart_options = {
    animation: {
        duration: 0
    },
    hover: {
        animationDuration: 0
    },
    responsiveAnimationDuration: 0,
    elements: {
        line: {
            tension: 0
        }
    },
    tooltips: {
        mode: 'index',
        intersect: false
    },
    title: {
        text: '',
        display: true
    },
    scales: {
        yAxes: [{
            gridLines: {
                display: false
            },
            ticks: {
                beginAtZero: true,
                userCallback: (value, index, values) => {
                    if (Math.floor(value) === value) {
                        return nr(value);
                    }
                },
            }
        }],
        xAxes: [{
            gridLines: {
                display: false
            },
            ticks: {
                callback: (tick, index, array) => {
                    return index % 2 ? '' : tick;
                }
            }
        }]
    },
    responsive: true,
    maintainAspectRatio: false
};
