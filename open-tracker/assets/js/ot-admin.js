/**
 * OpenTracker — Admin Dashboard Charts
 *
 * Initialises Chart.js line chart for the visits-over-time graph.
 *
 * @package OpenTracker
 */
(function () {
	'use strict';

	var data = window.otAdminData;
	if ( ! data ) {
		return;
	}

	function init() {
		var canvas = document.getElementById( 'ot-visits-chart' );
		if ( ! canvas ) {
			return;
		}

		var ctx = canvas.getContext( '2d' );

		// Gradient fill.
		var gradient = ctx.createLinearGradient( 0, 0, 0, 300 );
		gradient.addColorStop( 0, 'rgba(59, 130, 246, 0.15)' );
		gradient.addColorStop( 1, 'rgba(59, 130, 246, 0.01)' );

		new Chart( ctx, {
			type: 'line',
			data: {
				labels: data.chartLabels,
				datasets: [{
					label: 'Visits',
					data: data.chartValues,
					borderColor: '#3b82f6',
					backgroundColor: gradient,
					borderWidth: 2.5,
					fill: true,
					tension: 0.35,
					pointRadius: 3,
					pointBackgroundColor: '#3b82f6',
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
					pointHoverRadius: 6,
					pointHoverBackgroundColor: '#3b82f6',
					pointHoverBorderColor: '#fff',
					pointHoverBorderWidth: 3,
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					intersect: false,
					mode: 'index',
				},
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						backgroundColor: '#1e293b',
						titleColor: '#f8fafc',
						bodyColor: '#f8fafc',
						padding: 12,
						cornerRadius: 8,
						displayColors: false,
						titleFont: { size: 13, weight: '600' },
						bodyFont: { size: 14 },
						callbacks: {
							title: function ( items ) {
								return items[0].label;
							},
							label: function ( item ) {
								return item.formattedValue + ' visits';
							}
						}
					}
				},
				scales: {
					x: {
						grid: {
							display: false,
						},
						ticks: {
							font: { size: 11 },
							color: '#94a3b8',
							maxRotation: 45,
						},
						border: {
							display: false,
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: '#f1f5f9',
						},
						ticks: {
							font: { size: 11 },
							color: '#94a3b8',
							precision: 0,
						},
						border: {
							display: false,
						}
					}
				}
			}
		});
	}

	// Run when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

})();
