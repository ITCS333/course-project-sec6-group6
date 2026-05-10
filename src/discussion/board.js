let topics = [];

const newTopicForm       = document.getElementById('new-topic-form');
const topicListContainer = document.getElementById('topic-list-container');

function createTopicArticle(topic) {
  var article = document.createElement('article');
  article.innerHTML = `
    <h3><a href="topic.html?id=${topic.id}">${topic.subject}</a></h3>
    <p>${topic.message}</p>
    <footer>${topic.author} &mdash; ${topic.created_at}</footer>
    <button class="delete-btn" data-id="${topic.id}">Delete</button>`;
  return article;
}

function renderTopics() {
  topicListContainer.innerHTML = '';
  topics.forEach(function(topic) {
    topicListContainer.appendChild(createTopicArticle(topic));
  });
}

async function handleUpdateTopic(id, fields) {
  var res    = await fetch('./api/index.php', {
    method:  'PUT',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ id: id, subject: fields.subject, message: fields.message }),
  });
  var result = await res.json();
  if (result.success) {
    topics = topics.map(function(t) {
      return String(t.id) === String(id) ? Object.assign({}, t, fields) : t;
    });
    renderTopics();
  }
}

function handleCreateTopic(event) {
  event.preventDefault();

  var subject = document.getElementById('topic-subject').value.trim();
  var message = document.getElementById('topic-message').value.trim();

  if (!subject || !message) {
    alert('Please fill out all fields.');
    return;
  }

  var user   = JSON.parse((typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('user') : null) || '{}');
  var author = user.name || 'Anonymous';

  fetch('./api/index.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ subject: subject, message: message, author: author }),
  })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        loadAndInitialize();
        newTopicForm.reset();
      } else {
        alert(data.message || 'Failed to create topic.');
      }
    });
}

function handleTopicListClick(event) {
  var target = event.target;

  if (target.classList.contains('delete-btn')) {
    var id = target.dataset.id;
    fetch('./api/index.php?id=' + id, { method: 'DELETE' })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data.success) {
          topics = topics.filter(function(t) { return String(t.id) !== String(id); });
          renderTopics();
        } else {
          alert(data.message || 'Failed to delete topic.');
        }
      });
  }
}

async function loadAndInitialize() {
  var response = await fetch('./api/index.php');

  if (!response.ok) {
    alert('Failed to load topics.');
    return;
  }

  var json = await response.json();
  topics   = json.data;
  renderTopics();

  if (!loadAndInitialize._listenersAttached) {
    newTopicForm.addEventListener('submit', handleCreateTopic);
    topicListContainer.addEventListener('click', handleTopicListClick);
    loadAndInitialize._listenersAttached = true;
  }
}

loadAndInitialize._listenersAttached = false;
loadAndInitialize();
