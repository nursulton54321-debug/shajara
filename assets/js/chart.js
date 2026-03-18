// =============================================
// FILE: assets/js/chart.js
// MAQSAD: Chart.js yordamida diagrammalar chizish
// =============================================

// Diagramma obyektlari
let jinsChart = null;
let yoshChart = null;
let oylarChart = null;

/**
 * Jins diagrammasini chizish
 * @param {Object} data - Jins ma'lumotlari
 */
function chizJinsDiagramma(data) {
    const ctx = document.getElementById('jinsChart').getContext('2d');
    
    // Eski diagrammani o'chirish
    if (jinsChart) {
        jinsChart.destroy();
    }
    
    jinsChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: [
                    '#4299e1',  // Erkak - ko'k
                    '#ed64a6'   // Ayol - pushti
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            family: 'Segoe UI',
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Yosh guruhlari diagrammasini chizish
 * @param {Object} data - Yosh ma'lumotlari
 */
function chizYoshDiagramma(data) {
    const ctx = document.getElementById('yoshChart').getContext('2d');
    
    // Eski diagrammani o'chirish
    if (yoshChart) {
        yoshChart.destroy();
    }
    
    yoshChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(data),
            datasets: [{
                label: 'Shaxslar soni',
                data: Object.values(data),
                backgroundColor: '#667eea',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Tug'ilgan oylar diagrammasini chizish
 * @param {Object} data - Oylar ma'lumotlari
 */
function chizOylarDiagramma(data) {
    const ctx = document.getElementById('oylarChart').getContext('2d');
    
    // Oylar nomlari
    const oylar = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyun', 'Iyul', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
    
    // Ma'lumotlarni massivga aylantirish
    const oyData = [];
    for (let i = 1; i <= 12; i++) {
        oyData.push(data[i] || 0);
    }
    
    // Eski diagrammani o'chirish
    if (oylarChart) {
        oylarChart.destroy();
    }
    
    oylarChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: oylar,
            datasets: [{
                label: 'Tug\'ilganlar soni',
                data: oyData,
                borderColor: '#48c78e',
                backgroundColor: 'rgba(72, 199, 142, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#48c78e',
                pointBorderColor: 'white',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                }
            }
        }
    });
}

// Funksiyalarni global qilish
window.chizJinsDiagramma = chizJinsDiagramma;
window.chizYoshDiagramma = chizYoshDiagramma;
window.chizOylarDiagramma = chizOylarDiagramma;

// Sahifa yuklanganda statistika paneli uchun diagrammalarni tayyorlash
document.addEventListener('DOMContentLoaded', function() {
    // Statistika paneli ochilganda diagrammalar avtomatik yuklanadi
    // (statistikaYukla() funksiyasi orqali)
});