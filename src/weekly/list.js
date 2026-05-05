/*
  Requirement: Populate the "Weekly Course Breakdown" list page.

  Instructions:
  1. This file is already linked to `list.html` via:
         <script src="list.js" defer></script>

  2. In `list.html`, the <section id="week-list-section"> is the container
     that this script populates.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
const weekListSection = document.getElementById('week-list-section');

// --- Functions ---

/**
 * Creates an <article> element for a week.
 */
function createWeekArticle(week) {
  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = week.title;

  const pDate = document.createElement('p');
  pDate.textContent = 'Starts on: ' + week.start_date;

  const pDesc = document.createElement('p');
  pDesc.textContent = week.description;

  const a = document.createElement('a');
  a.href = 'details.html?id=' + week.id;
  a.textContent = 'View Details & Discussion';

  article.appendChild(h2);
  article.appendChild(pDate);
  article.appendChild(pDesc);
  article.appendChild(a);

  return article;
}

/**
 * Fetches weeks from the API and renders them.
 */
async function loadWeeks() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  weekListSection.innerHTML = '';

  if (result.success && result.data) {
    for (const week of result.data) {
      weekListSection.appendChild(createWeekArticle(week));
    }
  }
}

// --- Initial Page Load ---
loadWeeks();
