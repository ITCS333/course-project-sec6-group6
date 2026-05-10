let currentTopicId = null;
let currentReplies = [];

const topicSubject        = document.getElementById('topic-subject');
const opMessage           = document.getElementById('op-message');
const opFooter            = document.getElementById('op-footer');
const replyListContainer  = document.getElementById('reply-list-container');
const replyForm           = document.getElementById('reply-form');
const newReplyText        = document.getElementById('new-reply');

function getTopicIdFromURL() {
  var params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderOriginalPost(topic) {
  topicSubject.textContent = topic.subject;
  opMessage.textContent    = topic.message;
  opFooter.textContent     = 'Posted by: ' + topic.author + ' on ' + topic.created_at;
}

function createReplyArticle(reply) {
  var article = document.createElement('article');
  article.innerHTML = `
    <p>${reply.text}</p>
    <footer>Posted by: ${reply.author} on ${reply.created_at}</footer>
    <div>
      <button class="delete-reply-btn" data-id="${reply.id}">Delete</button>
    </div>`;
  return article;
}

function renderReplies() {
  replyListContainer.innerHTML = '';
  currentReplies.forEach(function(reply) {
    replyListContainer.appendChild(createReplyArticle(reply));
  });
}

async function handleAddReply(event) {
  event.preventDefault();

  var text = newReplyText.value.trim();
  if (!text) return;

  var user   = JSON.parse((typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('user') : null) || '{}');
  var author = user.name || 'Student';

  var res    = await fetch('./api/index.php?action=reply', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ topic_id: currentTopicId, author: author, text: text }),
  });
  var result = await res.json();

  if (result.success) {
    currentReplies.push(result.data);
    renderReplies();
    newReplyText.value = '';
  } else {
    alert(result.message || 'Failed to post reply.');
  }
}

async function handleReplyListClick(event) {
  var target = event.target;

  if (target.classList.contains('delete-reply-btn')) {
    var id     = target.dataset.id;
    var res    = await fetch('./api/index.php?action=delete_reply&id=' + id, { method: 'DELETE' });
    var result = await res.json();

    if (result.success) {
      currentReplies = currentReplies.filter(function(r) { return String(r.id) !== String(id); });
      renderReplies();
    } else {
      alert(result.message || 'Failed to delete reply.');
    }
  }
}

async function initializePage() {
  currentTopicId = getTopicIdFromURL();

  if (!currentTopicId) {
    topicSubject.textContent = 'Topic not found.';
    return;
  }

  var [topicRes, repliesRes] = await Promise.all([
    fetch('./api/index.php?id=' + currentTopicId),
    fetch('./api/index.php?action=replies&topic_id=' + currentTopicId),
  ]);

  var topicJson   = await topicRes.json();
  var repliesJson = await repliesRes.json();

  if (topicJson.success && topicJson.data) {
    renderOriginalPost(topicJson.data);
    currentReplies = repliesJson.success ? repliesJson.data : [];
    renderReplies();
    replyForm.addEventListener('submit', handleAddReply);
    replyListContainer.addEventListener('click', handleReplyListClick);
  } else {
    topicSubject.textContent = 'Topic not found.';
  }
}

initializePage();
