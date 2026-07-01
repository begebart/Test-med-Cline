document.getElementById('validatorForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const content = document.getElementById('content').value;
    const resultsDiv = document.getElementById('results');
    resultsDiv.innerHTML = '<p>Validating links...</p>';

    fetch('validate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `content=${encodeURIComponent(content)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.results && data.results.length > 0) {
                let html = '<h3>Validation Results:</h3><ul>';
                data.results.forEach(result => {
                    const status = result.valid ? '✅ Valid' : '❌ Invalid';
                    html += `<li>${status}: ${result.url}</li>`;
                });
                html += '</ul>';
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.innerHTML = '<p>No links found.</p>';
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = `<p>Error: ${error.message}</p>`;
        });
});