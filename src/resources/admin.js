/*
  Requirement: Make the "Manage Resources" page interactive.
*/

// --- Global Data Store ---
window.resources = [];

// --- Element Selections ---
const form = document.getElementById("resource-form");
const tbody = document.getElementById("resources-tbody");

// --- Functions ---

function createResourceRow(resource) {
  let tr = document.createElement("tr");

  let tdTitle = document.createElement("td");
  tdTitle.textContent = resource.title;

  let tdDesc = document.createElement("td");
  tdDesc.textContent = resource.description;

  let tdLink = document.createElement("td");
  tdLink.textContent = resource.link;

  let tdActions = document.createElement("td");

  let editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.dataset.id = resource.id;

  let deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = resource.id;

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDesc);
  tr.appendChild(tdLink);
  tr.appendChild(tdActions);

  return tr;
}

// --- FIXED HERE ---
function renderTable() {
  const tbody = document.getElementById("resources-tbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  window.resources.forEach(resource => {
    const row = createResourceRow(resource);
    tbody.appendChild(row);
  });
}

function handleAddResource(event) {
  event.preventDefault();

  const title = document.getElementById("resource-title").value;
  const description = document.getElementById("resource-description").value;
  const link = document.getElementById("resource-link").value;

  const editId = form.dataset.editId;

  // UPDATE
  if (editId) {
    fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: editId, title, description, link })
    }).then(res => res.json())
      .then(result => {
        if (result.success) {
          const index = window.resources.findIndex(r => r.id == editId);

          window.resources[index] = {
            id: editId,
            title,
            description,
            link
          };

          renderTable();

          form.reset();
          delete form.dataset.editId;
          document.getElementById("add-resource").textContent = "Add Resource";
        }
      });

    return;
  }

  // CREATE
  fetch("./api/index.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ title, description, link })
  })
    .then(res => res.json())
    .then(result => {
      window.resources.push({
        id: result.id,
        title,
        description,
        link
      });

      renderTable();
      form.reset();
    });
}

function handleTableClick(event) {
  const button = event.target.closest("button");
  if (!button) return;

  const id = button.dataset.id;

  // DELETE
  if (button.classList.contains("delete-btn")) {
    fetch(`./api/index.php?id=${id}`, {
      method: "DELETE"
    }).then(() => {
      window.resources = window.resources.filter(r => r.id != id);
      renderTable();
    });
  }

  // EDIT
  if (button.classList.contains("edit-btn")) {
    const resource = window.resources.find(r => r.id == id);
    if (!resource) return;

    document.getElementById("resource-title").value = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value = resource.link;

    form.dataset.editId = id;
    document.getElementById("add-resource").textContent = "Update Resource";
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  window.resources = result.success ? result.data : [];

  renderTable();

  form.addEventListener("submit", handleAddResource);
  tbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();