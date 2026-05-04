/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];

// --- Element Selections ---
const tbody = document.getElementById('resources-tbody');
const form = document.getElementById('resource-form');
const submitBtn = document.getElementById('add-resource');

// --- Functions ---

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class="edit-btn" and data-id="${id}".
 *    - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
function createResourceRow({ id, title, description, link }) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${title}</td>
    <td>${description}</td>
    <td><a href="${link}" target="_blank">${link}</a></td>
    <td>
      <button class="edit-btn" data-id="${id}">Edit</button>
      <button class="delete-btn" data-id="${id}">Delete</button>
    </td>
  `;
  return tr;
}


/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global `resources` array.
 * 3. For each resource, call `createResourceRow()` and
 *    append the returned <tr> to the table body.
 */
function renderTable() {
  tbody.innerHTML = '';
  resources.forEach(resource => {
    tbody.appendChild(createResourceRow(resource));
  });
}
/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title (id="resource-title"),
 *    description (id="resource-description"), and
 *    link (id="resource-link") inputs.
 * 3. Use `fetch()` to POST the new resource to the API:
 *    - URL: './api/index.php'
 *    - Method: POST
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ title, description, link })
 * 4. The API returns { success: true, id: <new id> }.
 *    Add the new resource object (including the id returned by the API)
 *    to the global `resources` array.
 * 5. Call `renderTable()` to refresh the list.
 * 6. Reset the form.
 */
function handleAddResource(e) {
  e.preventDefault();
  const title = document.getElementById('resource-title').value;
  const description = document.getElementById('resource-description').value;
  const link = document.getElementById('resource-link').value;

  fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, description, link })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        resources.push({ id: data.id, title, description, link });
        renderTable();
        form.reset();
      }
    });
}


/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 * It should:
 *
 * If the clicked element has class "delete-btn":
 * 1. Get the resource id from the button's data-id attribute.............
 * 2. Use `fetch()` to DELETE the resource via the API:
 *    - URL: `./api/index.php?id=${id}`
 *    - Method: DELETE
 * 3. On success, remove the resource from the global `resources` array
 *    by filtering out the entry with the matching id.
 * 4. Call `renderTable()` to refresh the list.
 *
 * If the clicked element has class "edit-btn":
 * 
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Find the matching resource in the global `resources` array.
 * 3. Populate the form fields (id="resource-title", id="resource-description",
 *    id="resource-link") with the resource's current values so the admin
 *    can edit them.
 * 4. Change the submit button (id="add-resource") text to "Update Resource"
 *    to indicate edit mode.
 * \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
 * 5. On form submit, use `fetch()` to PUT the updated resource to the API:
 *    - URL: './api/index.php'
 *    - Method: PUT
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ id, title, description, link })
 * 6. On success, update the matching resource in the global `resources` array.
 * 7. Call `renderTable()` and reset the form back to "Add" mode,
 *    restoring the submit button text to "Add Resource".
 */
function handleTableClick(e) {
  if (e.target.classList.contains('delete-btn')) {
    const id = e.target.dataset.id;
    fetch(`./api/index.php?id=${id}`, { method: 'DELETE' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          resources = resources.filter(r => r.id != id);
          renderTable();
        }
      });
  }

  if (e.target.classList.contains('edit-btn')) {
    const id = e.target.dataset.id;
    const resource = resources.find(r => r.id == id);
    if (!resource) return;

    document.getElementById('resource-title').value = resource.title;
    document.getElementById('resource-description').value = resource.description;
    document.getElementById('resource-link').value = resource.link;
    submitBtn.textContent = 'Update Resource';

    const updatedHandler = (e) => {
      e.preventDefault();
      const title = document.getElementById('resource-title').value;
      const description = document.getElementById('resource-description').value;
      const link = document.getElementById('resource-link').value;

      fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, title, description, link })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            const idx = resources.findIndex(r => r.id == id);
            resources[idx] = { id, title, description, link };
            renderTable();
            form.reset();
            submitBtn.textContent = 'Add Resource';
            form.removeEventListener('submit', updatedHandler);
            form.addEventListener('submit', handleAddResource);
          }
        });
    };

    form.removeEventListener('submit', handleAddResource);
    form.addEventListener('submit', updatedHandler);
  }
}

/**
 * TODO: Implement the loadAndInitialize function.
 * This function must be 'async'.
 * It should:
 * 1. Use `fetch()` to GET all resources from the API:
 *    - URL: './api/index.php'
 *    - The API returns { success: true, data: [...] }
 * 2. Store the resources array (from `data`) in the global `resources` variable.
 * 3. Call `renderTable()` to populate the table for the first time.
 * 4. Add the 'submit' event listener to the resource form (id="resource-form"),
 *    calling `handleAddResource`.
 * 5. Add the 'click' event listener to the table body (id="resources-tbody"),
 *    calling `handleTableClick`.
 */
async function loadAndInitialize() {
  const res = await fetch('./api/index.php');
  const data = await res.json();
  resources = data.data;
  renderTable();
  form.addEventListener('submit', handleAddResource);
  tbody.addEventListener('click', handleTableClick);
}


// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
