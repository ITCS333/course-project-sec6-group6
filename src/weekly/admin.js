/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="week-form".
     - The submit button has id="add-week".
     - The <tbody> has id="weeks-tbody".
     - Columns rendered per row: Week Title | Start Date | Description | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...week objects ] }
  Each week object shape:
    {
      id:          number,   // integer primary key from the weeks table
      title:       string,
      start_date:  string,   // "YYYY-MM-DD"
      description: string,
      links:       string[]  // decoded array of URL strings
    }
*/

// --- Global Data Store ---
// Holds the weeks currently displayed in the table.
let weeks = [];

// --- Element Selections ---
const weekForm = document.getElementById('week-form');
const weeksTbody = document.getElementById('weeks-tbody');

// --- Functions ---

/**
 * Creates a table row for a week.
 */
function createWeekRow(week) {
  const tr = document.createElement('tr');

  const tdTitle = document.createElement('td');
  tdTitle.textContent = week.title;

  const tdDate = document.createElement('td');
  tdDate.textContent = week.start_date;

  const tdDesc = document.createElement('td');
  tdDesc.textContent = week.description;

  const tdActions = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = week.id;
  editBtn.textContent = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = week.id;
  deleteBtn.textContent = 'Delete';

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDate);
  tr.appendChild(tdDesc);
  tr.appendChild(tdActions);

  return tr;
}

/**
 * Renders the weeks table from the global weeks array.
 */
function renderTable() {
  weeksTbody.innerHTML = '';
  for (const week of weeks) {
    weeksTbody.appendChild(createWeekRow(week));
  }
}

/**
 * Handles form submission for adding or updating a week.
 */
async function handleAddWeek(event) {
  event.preventDefault();

  const title = document.getElementById('week-title').value;
  const start_date = document.getElementById('week-start-date').value;
  const description = document.getElementById('week-description').value;
  const linksRaw = document.getElementById('week-links').value;
  const links = linksRaw.split('\n').filter(line => line.trim() !== '');

  const submitBtn = document.getElementById('add-week');
  const editId = submitBtn.dataset.editId;

  if (editId) {
    await handleUpdateWeek(parseInt(editId), { title, start_date, description, links });
  } else {
    const response = await fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, start_date, description, links })
    });
    const result = await response.json();

    if (result.success) {
      weeks.push({
        id: result.id,
        title,
        start_date,
        description,
        links
      });
      renderTable();
      weekForm.reset();
    }
  }
}

/**
 * Handles updating an existing week via PUT.
 */
async function handleUpdateWeek(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...fields })
  });
  const result = await response.json();

  if (result.success) {
    const index = weeks.findIndex(w => w.id === id);
    if (index !== -1) {
      weeks[index] = { ...weeks[index], ...fields };
    }
    renderTable();
    weekForm.reset();

    const submitBtn = document.getElementById('add-week');
    submitBtn.textContent = 'Add Week';
    delete submitBtn.dataset.editId;
  }
}

/**
 * Delegated click handler for edit and delete buttons.
 */
async function handleTableClick(event) {
  if (event.target.classList.contains('delete-btn')) {
    const id = parseInt(event.target.dataset.id);
    const response = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });
    const result = await response.json();

    if (result.success) {
      weeks = weeks.filter(w => w.id !== id);
      renderTable();
    }
  }

  if (event.target.classList.contains('edit-btn')) {
    const id = parseInt(event.target.dataset.id);
    const week = weeks.find(w => w.id === id);
    if (!week) return;

    document.getElementById('week-title').value = week.title;
    document.getElementById('week-start-date').value = week.start_date;
    document.getElementById('week-description').value = week.description;
    document.getElementById('week-links').value = (week.links || []).join('\n');

    const submitBtn = document.getElementById('add-week');
    submitBtn.textContent = 'Update Week';
    submitBtn.dataset.editId = week.id;
  }
}

/**
 * Loads weeks from the API and initializes event listeners.
 */
async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (result.success) {
    weeks = result.data;
  }

  renderTable();
  weekForm.addEventListener('submit', handleAddWeek);
  weeksTbody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
