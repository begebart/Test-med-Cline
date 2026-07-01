<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Velkendt.com - Tjek om din SMS eller Email er ægte</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>🛡️ Velkendt.com</h1>
            <p class="tagline">Tjek om din SMS eller Email er ægte - Hurtig og nem</p>
        </div>
    </header>

    <main class="container">
        <!-- How to use section -->
        <section class="how-to-use">
            <h2>📱 Sådan bruger du værktøjet</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Kopier beskeden</h3>
                        <p>Markér hele den mistænkelige SMS eller email og kopier den</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Indsæt her</h3>
                        <p>Klik i tekstfeltet nedenfor og indsæt (Ctrl+V eller højre-klik → Indsæt)</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Vælg type</h3>
                        <p>Vælg om det er en SMS eller Email</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Klik "Tjek nu"</h3>
                        <p>Vi analyserer beskeden og fortæller dig om den er troværdig</p>
                        <p><strong>Test:</strong> Åbn F12 for at se fejlmeddelelser hvis det ikke virker</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main analysis tool -->
        <section class="analyzer">
            <h2>🔍 Tjek din besked</h2>
            
            <form id="analyzeForm" method="POST" action="resultat.php">
                <div class="form-group">
                    <label for="messageType">Hvad er det for en type besked?</label>
                    <select id="messageType" name="type">
                        <option value="email">📧 Email</option>
                        <option value="sms">📱 SMS</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="messageText">Indsæt hele beskeden her:</label>
                    <textarea 
                        id="messageText" 
                        name="text" 
                        rows="10" 
                        placeholder="Kopier og indsæt hele den mistænkelige besked her..."
                        required
                    ></textarea>
                    <div class="char-count">
                        <span id="charCount">0</span> tegn
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="analyzeBtn">
                    <span class="btn-text">🔍 Tjek nu</span>
                </button>
            </form>

            <!-- Results section -->
            <div id="results" class="results" style="display: none;">
                <div class="result-header">
                    <h3>📊 Resultat</h3>
                    <div id="verdict" class="verdict"></div>
                </div>

                <div class="score-container">
                    <div class="score-circle">
                        <div class="score-value" id="scoreValue">0</div>
                        <div class="score-label">Risiko</div>
                    </div>
                </div>

                <div class="warnings-section" id="warningsSection" style="display: none;">
                    <h4>⚠️ Advarsler fundet:</h4>
                    <ul id="warningsList"></ul>
                </div>

                <div class="urls-section" id="urlsSection" style="display: none;">
                    <h4>🔗 Links fundet:</h4>
                    <div id="urlsList"></div>
                </div>

                <div class="emails-section" id="emailsSection" style="display: none;">
                    <h4>📧 Email-adresser fundet:</h4>
                    <div id="emailsList"></div>
                </div>

                <div class="recommendations-section" id="recommendationsSection" style="display: none;">
                    <h4>✅ Anbefalinger:</h4>
                    <ul id="recommendationsList"></ul>
                </div>

                <div class="result-actions">
                    <button class="btn-secondary" onclick="saveToForum()">
                        💾 Gem i forum
                    </button>
                    <button class="btn-secondary" onclick="copyResults()">
                        📋 Kopier resultat
                    </button>
                    <button class="btn-secondary" onclick="newAnalysis()">
                        🔄 Ny analyse
                    </button>
                </div>
            </div>
        </section>

        <!-- Forum section -->
        <section class="forum">
            <h2>📋 Arkiv - Delte analyser</h2>
            <p class="forum-intro">
                Se analyser som andre brugere har delt. Brug det til at genkende svindel.
            </p>
            <div style="text-align: center; padding: 20px;">
                <a href="arkiv.php" class="btn-primary" style="display: inline-block; text-decoration: none;">
                    📋 Gå til arkiv
                </a>
            </div>
        </section>

        <!-- Tips section -->
        <section class="tips">
            <h2>💡 Gode råd til at undgå svindel</h2>
            <div class="tips-grid">
                <div class="tip">
                    <div class="tip-icon">🔗</div>
                    <h3>Tjek altid linket</h3>
                    <p>Hvis du modtager et link, så hold musen over det (på computer) eller tryk langt på det (på telefon) for at se det rigtige webadresse. Er det .dk eller .com? Passer det til virksomheden?</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">📞</div>
                    <h3>Ring i stedet</h3>
                    <p>Hvis du er i tvivl, så ring til virksomheden på det telefonnummer du selv finder på deres hjemmeside - ikke på det nummer der står i beskeden.</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">🔒</div>
                    <h3>Del aldrig koder</h3>
                    <p>Ægte virksomheder spørger aldrig efter din adgangskode, CPR-nummer eller kortoplysninger via email eller SMS.</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">⏰</div>
                    <h3>Tag dig tid</h3>
                    <p>Svindlere vil have dig til at handle hurtigt. Tag dig tid til at tænke og tjekke. Du kan sagtens vente et par dage.</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">🌐</div>
                    <h3>Bekræft på hjemmesiden</h3>
                    <p>Skriv selv virksomhedens navn i din browser og gå til deres officielle hjemmeside. Derfra kan du logge ind sikkert.</p>
                </div>
                <div class="tip">
                    <div class="tip-icon">👨‍👩‍👧‍👦</div>
                    <h3>Spørg andre</h3>
                    <p>Hvis du er i tvivl, så spørg en familie eller ven. To hoder er bedre end et, især når det gælder sikkerhed.</p>
                </div>
            </div>
        </section>

        <!-- About section -->
        <section class="about">
            <h2>ℹ️ Om Velkendt.com</h2>
            <p>Denne hjemmeside er lavet for at hjælpe alle - især dem over 50 - med at identificere mistænkelige SMS og emails. Vi ved, at det kan være svært at spotte svindel, og her får du et simpelt værktøj til at tjekke dine beskeder.</p>
            <p><strong>Husk:</strong> Vi kan ikke garantere at resultatet er 100% korrekt. Brug din sunde fornuft og når i tvivl, så kontakt virksomheden direkte.</p>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>© 2026 Velkendt.com - Hjælper danskere med at undgå svindel</p>
            <p class="footer-note">Dette er et gratis værktøj. Vi gemmer ikke dine personlige oplysninger.</p>
        </div>
    </footer>

    <script src="js/app.js"></script>
</body>
</html>