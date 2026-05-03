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
let form = document.getElementById("resource-form");
let tbody = document.getElementById("resources-tbody");

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
function createResourceRow(resource) {
let tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description}</td>
    <td><a href="${resource.link}" target="_blank">Visit</a></td>
    <td>
      <button class="edit-btn" data-id="${resource.id}">Edit</button>
      <button class="delete-btn" data-id="${resource.id}">Delete</button>
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

  // 1. تفريغ الجدول
  tbody.innerHTML = "";

  // 2. المرور على كل resource
  resources.forEach(resource => {
   let row = createResourceRow(resource);

    // 3. إضافة الصف للجدول
    tbody.appendChild(row);
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
function handleAddResource(event) {

  event.preventDefault();

  let title = document.getElementById("resource-title").value;
  let description = document.getElementById("resource-description").value;
  let link = document.getElementById("resource-link").value;

  fetch('./api/index.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ title, description, link })
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      const newResource = {
        id: result.id,
        title,
        description,
        link
      };

      resources.push(newResource);
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
function handleTableClick(event) {
let id = event.target.dataset.id;

  if (event.target.classList.contains("delete-btn")) {
    fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        resources = resources.filter(r => r.id != id);
        renderTable();
      }
    });
  }


  if (event.target.classList.contains("edit-btn")) {
    let resource = resources.find(r => r.id == id);

    document.getElementById("resource-title").value = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value = resource.link;

    document.getElementById("add-resource").textContent = "Update Resource";

      form.dataset.editId = id;
  }


fetch('./api/index.php', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id,
        title: resource.title,
        description: resource.description,
        link: resource.link
      })
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {

       
        const index = resources.findIndex(r => r.id == id);

        resources[index] = {
          id,
          title: resource.title,
          description: resource.description,
          link: resource.link
        };

       
        renderTable();

        document.getElementById("resource-title").value = "";
        document.getElementById("resource-description").value = "";
        document.getElementById("resource-link").value = "";

        delete form.dataset.editId;

        document.getElementById("add-resource").textContent = "Add Resource";
      }
    });
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
  // GET all resources
  const response = await fetch('./api/index.php');
  const result = await response.json();

  // تخزين البيانات في المتغير العالمي
  if (result.success) {
    resources = result.data;
  }

  // عرض الجدول لأول مرة
  renderTable();

  //ربط الفورم (submit)
  const form = document.getElementById("resource-form");
  form.addEventListener("submit", handleAddResource);

  //ربط الجدول (click)
  const tbody = document.getElementById("resources-tbody");
  tbody.addEventListener("click", handleTableClick);
}


// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
