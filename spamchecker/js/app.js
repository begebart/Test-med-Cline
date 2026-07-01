// SpamChecker - Frontend JavaScript

// API endpoint
const API_URL = 'api.php';

// Current analysis results
let currentResults = null;
let currentText = '';
let currentType = 'email';

// DOM Elements
const analyzeForm = document.getElementById('analyzeForm');
const messageText = document.getElementById('messageText');
const messageType = document.getElementById('messageType');
const charCount = document.getElementById('charCount');
const analyzeBtn = document.getElementById('analyzeBtn');
const results = document.getElementById('results');
const reportsList = document.getElementById('reportsList');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('Velkendt.com - JavaScript loaded');
    console.log('API URL:', API_URL);
    
    // Character counter
    messageText.addEventListener('input', updateCharCount);
    
    // Form submission is now handled by HTML form (no JavaScript needed)
    // analyzeForm.addEventListener('submit', handleAnalyze);
    
    // Filter buttons
    const filterButtons = document.querySelectorAll('.btn-filter');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadReports(this.dataset.filter);
        });
    });
    
    // Load initial reports
    loadReports('all');
});

// Update character count
function updateCharCount() {
    const count = messageText.value.length;
    charCount.textContent = count;
}

// Handle form submission
async function handleAnalyze(e) {
    e.preventDefault();
    
    const text = messageText.value.trim();
    const type = messageType.value;
    
    if (!text) {
        alert('Indsæt venligst en besked at analysere');
        return;
    }
    
    currentText = text;
    currentType = type;
    
    // Show loading state
    setLoading(true);
    results.style.display = 'none';
    
    try {
        const url = new URL(API_URL, window.location.origin);
        url.searchParams.append('action', 'analyze');
        
        console.log('Sender request til:', url.toString());
        console.log('Data:', { text: text.substring(0, 50) + '...', type: type });
        
        const response = await fetch(url.toString(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                text: text,
                type: type
            })
        });
        
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('Response data:', data);
        
        if (data.success) {
            currentResults = data.results;
            displayResults(data.results);
            // Reload reports to show the new one
            loadReports('all');
        } else {
            alert('Fejl: ' + (data.error || 'Kunne ikke analysere beskeden'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Der opstod en fejl: ' + error.message);
    } finally {
        setLoading(false);
    }
}

// Set loading state
function setLoading(isLoading) {
    const btnText = analyzeBtn.querySelector('.btn-text');
    const btnLoading = analyzeBtn.querySelector('.btn-loading');
    
    if (isLoading) {
        analyzeBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline';
    } else {
        analyzeBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
}

// Display results
function displayResults(results) {
    // Update verdict
    const verdict = document.getElementById('verdict');
    verdict.textContent = results.verdict;
    verdict.className = 'verdict ' + results.verdict_class;
    
    // Update score
    const scoreValue = document.getElementById('scoreValue');
    const scoreCircle = document.querySelector('.score-circle');
    
    // Animate score
    animateScore(scoreValue, results.score);
    
    // Update score circle color
    updateScoreColor(scoreCircle, results.score);
    
    // Display warnings
    const warningsSection = document.getElementById('warningsSection');
    const warningsList = document.getElementById('warningsList');
    
    if (results.warnings && results.warnings.length > 0) {
        warningsSection.style.display = 'block';
        warningsList.innerHTML = results.warnings.map(w => 
            `<li><strong>${escapeHtml(w.warning)}</strong></li>`
        ).join('');
    } else {
        warningsSection.style.display = 'none';
    }
    
    // Display URLs
    const urlsSection = document.getElementById('urlsSection');
    const urlsList = document.getElementById('urlsList');
    
    if (results.urls && results.urls.length > 0) {
        urlsSection.style.display = 'block';
        urlsList.innerHTML = results.urls.map(url => {
            const statusClass = url.check.status;
            const statusText = getStatusText(url.check.status);
            return `
                <div class="url-item ${statusClass}">
                    <div class="url-domain">${escapeHtml(url.url)}</div>
                    <span class="url-status ${statusClass}">${escapeHtml(url.check.message)}</span>
                </div>
            `;
        }).join('');
    } else {
        urlsSection.style.display = 'none';
    }
    
    // Display emails
    const emailsSection = document.getElementById('emailsSection');
    const emailsList = document.getElementById('emailsList');
    
    if (results.emails && results.emails.length > 0) {
        emailsSection.style.display = 'block';
        emailsList.innerHTML = results.emails.map(email => `
            <div class="email-item">
                <strong>${escapeHtml(email.email)}</strong>
                <span style="color: #6b7280; margin-left: 10px;">(${escapeHtml(email.domain)})</span>
            </div>
        `).join('');
    } else {
        emailsSection.style.display = 'none';
    }
    
    // Display recommendations
    const recommendationsSection = document.getElementById('recommendationsSection');
    const recommendationsList = document.getElementById('recommendationsList');
    
    if (results.recommendations && results.recommendations.length > 0) {
        recommendationsSection.style.display = 'block';
        recommendationsList.innerHTML = results.recommendations.map(r => 
            `<li>${escapeHtml(r)}</li>`
        ).join('');
    } else {
        recommendationsSection.style.display = 'none';
    }
    
    // Show results
    results.style.display = 'block';
    
    // Scroll to results
    results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Animate score counter
function animateScore(element, targetScore) {
    let current = 0;
    const duration = 1000;
    const steps = 30;
    const increment = targetScore / steps;
    const stepTime = duration / steps;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= targetScore) {
            current = targetScore;
            clearInterval(timer);
        }
        element.textContent = Math.round(current);
    }, stepTime);
}

// Update score circle color
function updateScoreColor(element, score) {
    element.classList.remove('border-success', 'border-warning', 'border-danger', 'border-caution');
    
    if (score >= 70) {
        element.style.borderColor = '#ef4444';
    } else if (score >= 40) {
        element.style.borderColor = '#f59e0b';
    } else if (score >= 20) {
        element.style.borderColor = '#fbbf24';
    } else {
        element.style.borderColor = '#10b981';
    }
}

// Get status text
function getStatusText(status) {
    switch (status) {
        case 'legitimate':
            return '✓ Legitimt domæne';
        case 'suspicious':
            return '⚠️ Mistænkeligt';
        case 'caution':
            return '⚡ Vær forsigtig';
        default:
            return '? Ukendt';
    }
}

// Load reports from forum
async function loadReports(filter = 'all') {
    if (!reportsList) return;
    reportsList.innerHTML = '<div class="loading">⏳ Indlæser rapporter...</div>';
    
    try {
        const url = new URL(API_URL, window.location.origin);
        url.searchParams.append('action', 'reports');
        url.searchParams.append('limit', '50');
        // Tilføj timestamp for at undgå cache
        url.searchParams.append('_', Date.now());
        
        console.log('Henter rapporter fra:', url.toString());
        
        const response = await fetch(url.toString());
        
        if (!response.ok) {
            throw new Error('HTTP fejl: ' + response.status);
        }
        
        const data = await response.json();
        console.log('Rapporter modtaget:', data);
        
        if (data.success) {
            displayReports(data.reports, filter);
        } else {
            console.error('API fejl:', data.error);
            reportsList.innerHTML = '<div class="no-reports">API fejl: ' + (data.error || 'Ukendt') + '</div>';
        }
    } catch (error) {
        console.error('Fejl ved hentning af rapporter:', error);
        reportsList.innerHTML = '<div class="no-reports">Kunne ikke hente rapporter: ' + error.message + '</div>';
    }
}

// Display reports
function displayReports(reports, filter) {
    if (!reports || reports.length === 0) {
        reportsList.innerHTML = '<div class="no-reports">Ingen rapporter endnu. Vær den første til at teste en besked!</div>';
        return;
    }
    
    // Filter reports
    let filtered = reports;
    if (filter === 'email') {
        filtered = reports.filter(r => r.type === 'email');
    } else if (filter === 'sms') {
        filtered = reports.filter(r => r.type === 'sms');
    } else if (filter === 'high') {
        filtered = reports.filter(r => r.score >= 40);
    }
    
    if (filtered.length === 0) {
        reportsList.innerHTML = '<div class="no-reports">Ingen rapporter matcher filteret</div>';
        return;
    }
    
    reportsList.innerHTML = filtered.map(report => {
        const riskClass = report.score >= 70 ? 'high-risk' : 
                         report.score >= 40 ? 'medium-risk' : 'low-risk';
        const scoreClass = report.score >= 70 ? 'high' : 
                          report.score >= 40 ? 'medium' : 'low';
        
        return `
            <div class="report-card ${riskClass}">
                <div class="report-header">
                    <span class="report-type">${report.type === 'email' ? '📧 Email' : '📱 SMS'}</span>
                    <span class="report-timestamp">${formatDate(report.timestamp)}</span>
                </div>
                <div class="report-preview">${escapeHtml(report.text_preview)}</div>
                <div class="report-score">
                    <span class="score-badge ${scoreClass}">${report.score}% risiko</span>
                    <span class="report-verdict">${escapeHtml(report.verdict)}</span>
                </div>
                ${report.warning_count > 0 ? `
                    <div class="report-warnings">
                        <strong>${report.warning_count} advarsler fundet</strong>
                    </div>
                ` : ''}
                ${report.user_comment ? `
                    <div class="report-comment">
                        💬 <em>${escapeHtml(report.user_comment)}</em>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    // Less than 1 minute
    if (diff < 60000) {
        return 'Lige nu';
    }
    
    // Less than 1 hour
    if (diff < 3600000) {
        const minutes = Math.floor(diff / 60000);
        return `For ${minutes} minut siden`;
    }
    
    // Less than 1 day
    if (diff < 86400000) {
        const hours = Math.floor(diff / 3600000);
        return `For ${hours} time siden`;
    }
    
    // Less than 1 week
    if (diff < 604800000) {
        const days = Math.floor(diff / 86400000);
        return `For ${days} dag siden`;
    }
    
    // Otherwise show date
    return date.toLocaleDateString('da-DK', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Save to forum
async function saveToForum() {
    if (!currentResults || !currentText) {
        alert('Ingen analyse at gemme. Foretag venligst en analyse først.');
        return;
    }
    
    const comment = prompt('Evt. kommentar (f.eks. "Dette er en svindel jeg selv har modtaget"):');
    
    try {
        const url = new URL(API_URL, window.location.origin);
        url.searchParams.append('action', 'report');
        
        const response = await fetch(url.toString(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                text: currentText,
                type: currentType,
                comment: comment || ''
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Tak! Din rapport er gemt og kan nu ses af andre.');
            loadReports('all');
        } else {
            alert('Fejl: ' + (data.error || 'Kunne ikke gemme rapport'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Der opstod en fejl. Prøv igen senere.');
    }
}

// Copy results to clipboard
function copyResults() {
    if (!currentResults) {
        alert('Ingen resultater at kopiere');
        return;
    }
    
    const text = `
VELKENDT.COM - ANALYSERESULTAT
==============================

VURDERING: ${currentResults.verdict}
RISIKO: ${currentResults.score}%

ADVARSLER:
${currentResults.warnings.map(w => `• ${w.warning}`).join('\n')}

ANBEFALINGER:
${currentResults.recommendations.map(r => `• ${r}`).join('\n')}

---
Analyseret af Velkendt.com
    `.trim();
    
    navigator.clipboard.writeText(text).then(() => {
        alert('✅ Resultater kopieret til udklipsholder!');
    }).catch(err => {
        console.error('Kunne ikke kopiere:', err);
        alert('Kunne ikke kopiere. Prøv igen.');
    });
}

// New analysis
function newAnalysis() {
    messageText.value = '';
    currentResults = null;
    currentText = '';
    updateCharCount();
    results.style.display = 'none';
    messageText.focus();
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Service Worker for offline support (optional enhancement)
if ('serviceWorker' in navigator) {
    // Could be implemented for offline functionality
    // navigator.serviceWorker.register('/sw.js');
}

// Add touch support for mobile
document.addEventListener('touchstart', function() {}, { passive: true });

// Prevent zoom on double tap for iOS (optional)
let lastTouchEnd = 0;
document.addEventListener('touchend', function(event) {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);