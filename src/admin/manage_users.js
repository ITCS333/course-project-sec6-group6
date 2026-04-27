
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
  userArray.forEach(user => {
    userTableBody.appendChild(createUserRow(user));
  });
}

function handleChangePassword(event) {
  event.preventDefault();

  const currentPassword = document.getElementById('current-password').value;
  const newPassword     = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if (newPassword !== confirmPassword) {
    alert('Passwords do not match.');
    return;
  }
  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  const user = JSON.parse(sessionStorage.getItem('user') || '{}');
  const id   = user.id;

  fetch('../api/index.php?action=change_password', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id, current_password: currentPassword, new_password: newPassword }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Password updated successfully!');
        document.getElementById('current-password').value = '';
        document.getElementById('new-password').value     = '';
        document.getElementById('confirm-password').value = '';
      } else {
        alert(data.message);
      }
    });
}

function handleAddUser(event) {
  event.preventDefault();

  const name     = document.getElementById('user-name').value.trim();
  const email    = document.getElementById('user-email').value.trim();
  const password = document.getElementById('default-password').value;
  const is_admin = Number(document.getElementById('is-admin').value);

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
    body:    JSON.stringify({ name, email, password, is_admin }),
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        loadUsersAndInitialize();
        addUserForm.reset();
      } else {
        alert(data.message);
      }
    });
}

function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;
    fetch('../api/index.php?id=' + id, { method: 'DELETE' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          users = users.filter(u => String(u.id) !== String(id));
          renderTable(users);
        } else {
          alert(data.message);
        }
      });
  }

  if (target.classList.contains('edit-btn')) {
    const id   = target.dataset.id;
    const user = users.find(u => String(u.id) === String(id));
    if (!user) return;

    const newName = prompt('Edit name:', user.name);
    if (newName === null) return;

    fetch('../api/index.php', {
      method:  'PUT',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ id: user.id, name: newName.trim() }),
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          loadUsersAndInitialize();
        } else {
          alert(data.message);
        }
      });
  }
}

function handleSearch(event) {
  const term = searchInput.value.toLowerCase();

  if (term === '') {
    renderTable(users);
    return;
  }

  const filtered = users.filter(u =>
    u.name.toLowerCase().includes(term) ||
    u.email.toLowerCase().includes(term)
  );
  renderTable(filtered);
}

function handleSort(event) {
  const index = event.currentTarget.cellIndex;

  const keyMap = { 0: 'name', 1: 'email', 2: 'is_admin' };
  const key    = keyMap[index];
  if (!key) return;

  const th  = event.currentTarget;
  const dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
  th.setAttribute('data-sort-dir', dir);

  users.sort((a, b) => {
    if (key === 'is_admin') {
      return dir === 'asc' ? a[key] - b[key] : b[key] - a[key];
    }
    const cmp = a[key].localeCompare(b[key]);
    return dir === 'asc' ? cmp : -cmp;
  });

  renderTable(users);
}

async function loadUsersAndInitialize() {
  const response = await fetch('../api/index.php');

  if (!response.ok) {
    console.error('Failed to load users:', response.status);
    alert('Failed to load users. Please try again.');
    return;
  }

  const json = await response.json();
  users = json.data;
  renderTable(users);

  changePasswordForm.addEventListener('submit', handleChangePassword, { once: true });
  addUserForm.addEventListener('submit',        handleAddUser,        { once: true });
  userTableBody.addEventListener('click',       handleTableClick);
  searchInput.addEventListener('input',         handleSearch);
  tableHeaders.forEach(th => th.addEventListener('click', handleSort));
}


loadUsersAndInitialize();
