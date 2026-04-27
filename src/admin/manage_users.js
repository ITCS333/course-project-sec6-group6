let users = [];

const userTableBody      = document.getElementById('user-table-body');
const addUserForm        = document.getElementById('add-user-form');
const changePasswordForm = document.getElementById('password-form');
const searchInput        = document.getElementById('search-input');
const tableHeaders       = document.querySelectorAll('#user-table thead th');

function createUserRow(user) {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${user.name}</td>
    <td>${user.email}</td>
    <td>${user.is_admin === 1 ? 'Yes' : 'No'}</td>
    <td>
      <button class="edit-btn"   data-id="${user.id}">Edit</button>
      <button class="delete-btn" data-id="${user.id}">Delete</button>
    </td>`;
  return tr;
}

function renderTable(userArray) {
  userTableBody.innerHTML = '';
  userArray.forEach(function(user) {
    userTableBody.appendChild(createUserRow(user));
  });
}

function handleChangePassword(event) {
  event.preventDefault();

  var currentPasswordInput = document.getElementById('current-password');
  var newPasswordInput     = document.getElementById('new-password');
  var confirmPasswordInput = document.getElementById('confirm-password');

  var currentPassword = currentPasswordInput.value;
  var newPassword     = newPasswordInput.value;
  var confirmPassword = confirmPasswordInput.value;

  if (newPassword !== confirmPassword) {
    alert('Passwords do not match.');
    return;
  }
  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  currentPasswordInput.value = '';
  newPasswordInput.value     = '';
  confirmPasswordInput.value = '';

  var user = JSON.parse(sessionStorage.getItem('user') || '{}');
  var id   = user.id;

  fetch('../api/index.php?action=change_password', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id: id, current_password: currentPassword, new_password: newPassword }),
  })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        alert('Password updated successfully!');
      } else {
        alert(data.message);
      }
    });
}

function handleAddUser(event) {
  event.preventDefault();

  var name     = document.getElementById('user-name').value.trim();
  var email    = document.getElementById('user-email').value.trim();
  var password = document.getElementById('default-password').value;
  var is_admin = Number(document.getElementById('is-admin').value);

  if (!name || !email || !password) {
    alert('Please fill out all required fields.');
    return;
  }
  if (password.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  fetch('../api/index.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ name: name, email: email, password: password, is_admin: is_admin }),
  })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        loadUsersAndInitialize();
        addUserForm.reset();
      } else {
        alert(data.message);
      }
    });
}

function handleTableClick(event) {
  var target = event.target;

  if (target.classList.contains('delete-btn')) {
    var id = target.dataset.id;
    fetch('../api/index.php?id=' + id, { method: 'DELETE' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          users = users.filter(function(u) { return String(u.id) !== String(id); });
          renderTable(users);
        } else {
          alert(data.message);
        }
      });
  }

  if (target.classList.contains('edit-btn')) {
    var editId   = target.dataset.id;
    var editUser = users.find(function(u) { return String(u.id) === String(editId); });
    if (!editUser) return;

    var newName = prompt('Edit name:', editUser.name);
    if (newName === null) return;

    fetch('../api/index.php', {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: editUser.id, name: newName.trim() }),
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          loadUsersAndInitialize();
        } else {
          alert(data.message);
        }
      });
  }
}

function handleSearch(event) {
  var term = searchInput.value.toLowerCase();

  if (term === '') {
    renderTable(users);
    return;
  }

  var filtered = users.filter(function(u) {
    return u.name.toLowerCase().includes(term) ||
           u.email.toLowerCase().includes(term);
  });
  renderTable(filtered);
}

function handleSort(event) {
  var index = event.currentTarget.cellIndex;

  var keyMap = { 0: 'name', 1: 'email', 2: 'is_admin' };
  var key    = keyMap[index];
  if (!key) return;

  var th  = event.currentTarget;
  var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
  th.setAttribute('data-sort-dir', dir);

  users.sort(function(a, b) {
    if (key === 'is_admin') {
      return dir === 'asc' ? a[key] - b[key] : b[key] - a[key];
    }
    var cmp = a[key].localeCompare(b[key]);
    return dir === 'asc' ? cmp : -cmp;
  });

  renderTable(users);
}

async function loadUsersAndInitialize() {
  var response = await fetch('../api/index.php');

  if (!response.ok) {
    console.error('Failed to load users:', response.status);
    alert('Failed to load users. Please try again.');
    return;
  }

  var json = await response.json();
  users = json.data;
  renderTable(users);

  if (!loadUsersAndInitialize._listenersAttached) {
    changePasswordForm.addEventListener('submit', handleChangePassword);
    addUserForm.addEventListener('submit',        handleAddUser);
    userTableBody.addEventListener('click',       handleTableClick);
    searchInput.addEventListener('input',         handleSearch);
    tableHeaders.forEach(function(th) { th.addEventListener('click', handleSort); });
    loadUsersAndInitialize._listenersAttached = true;
  }
}

loadUsersAndInitialize._listenersAttached = false;
loadUsersAndInitialize();
