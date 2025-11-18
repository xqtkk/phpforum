const CSRF_TOKEN = "<?php echo $_SESSION['csrf']; ?>";

function timeAgo(date) {
    const now = new Date();
    const diff = (now - date) / 1000; // в секундах

    // секунды
    if (diff < 60) return "только что";

    // минуты
    const minutes = diff / 60;
    if (minutes < 60) {
        const m = Math.floor(minutes);
        return m + " " + ("мин.");
    }

    // часы
    const hours = minutes / 60;
    if (hours < 24) {
        const h = Math.floor(hours);
        return h + " " + ("ч.");
    }

    // дни
    const days = hours / 24;
    if (days < 2) return "вчера";

    if (days < 7) {
        const d = Math.floor(days);
        return d + " " + ("дн.");
    }

    // если давно — выводим дату
    return String(date.getDate()).padStart(2, '0') + "." +
        String((date.getMonth() + 1)).padStart(2, '0') + "." +
        String(date.getFullYear()).slice(-2);

}

document.querySelectorAll(".post-date").forEach(el => {
    const ts = el.dataset.time;

    // "2025-02-07T12:10:00" — нормальный формат
    const date = new Date(el.dataset.time); // будет уже с учётом UTC → локально


    el.textContent = timeAgo(date);
});

document.querySelectorAll(".comment-date").forEach(el => {
    const date = new Date(el.dataset.time);
    el.textContent = timeAgo(date);
});

document.addEventListener("click", e => {
    if (e.target.classList.contains("reply-link")) {
        e.preventDefault();
        const parentId = e.target.dataset.id;
        
        const form = e.target.closest(".comments").querySelector(".comment-form");
        form.querySelector("input[name=parent_id]").value = parentId;

        form.querySelector(".comment-input").placeholder = "Ответ на комментарий #" + parentId;
        form.scrollIntoView({behavior:"smooth"});
    }
});


function likePost(id, el) {
    fetch("like.php?id=" + id)
        .then(r => r.text())
        .then(t => {
            let counter = el.querySelector(".like-count");
            let icon = el.querySelector(".like-icon");

            if (t === "LIKED") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";
            } 
            else if (t === "UNLIKED") {
                counter.textContent = parseInt(counter.textContent) - 1;
                icon.style.opacity = "0.5";
            } 
            else if (t === "NOT_LOGGED_IN") {
                alert("Чтобы ставить лайки, войдите в аккаунт.");
            }
            if (t === "LIKED_REMOVED_DISLIKE") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";

                // Сбросить дизлайк
                let dSpan = el.parentNode.querySelector(".dislike-count");
                let dIcon = el.parentNode.querySelector(".dislike-icon");
                dIcon.style.opacity = "0.5";
                dSpan.textContent = parseInt(dSpan.textContent) - 1;
            }

        });
}   

function dislikePost(id, el) {
    fetch("dislike.php?id=" + id)
        .then(r => r.text())
        .then(t => {
            let counter = el.querySelector(".dislike-count");
            let icon = el.querySelector(".dislike-icon");

            if (t === "DISLIKED") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";
            } 
            else if (t === "UNDISLIKED") {
                counter.textContent = parseInt(counter.textContent) - 1;
                icon.style.opacity = "0.5";
            } 
            else if (t === "NOT_LOGGED_IN") {
                alert("Чтобы ставить дизлайки, войдите в аккаунт.");
            }
            if (t === "DISLIKED_REMOVED_LIKE") {
                counter.textContent = parseInt(counter.textContent) + 1;
                icon.style.opacity = "1";

                let lSpan = el.parentNode.querySelector(".like-count");
                let lIcon = el.parentNode.querySelector(".like-icon");
                lIcon.style.opacity = "0.5";
                lSpan.textContent = parseInt(lSpan.textContent) - 1;
            }

        });
}   

function addComment(e, postId) {
    e.preventDefault();
    const form = e.target;
    const input = form.querySelector('input[name="comment"]');
    const parentId = form.querySelector('input[name="parent_id"]').value;
    const content = input.value.trim();

    fetch('add_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + postId +
              '&parent_id=' + parentId +
              '&content=' + encodeURIComponent(content)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return alert(data.error);

        const list = form.parentNode.querySelector('.comment-list');

        const div = document.createElement("div");
        div.className = "comment";
        div.dataset.id = data.id;
        div.style.marginLeft = (parentId != 0 ? "25px" : "0");

        div.innerHTML = `
            <img src="avatars/${data.avatar}" class="comment-avatar">
            <div>
                <b>${data.username}</b>: ${data.content}
                <div class="comment-date" data-time="${data.created_at.replace(' ', 'T')}Z"></div>
                <div><a href="#" class="reply-link" data-id="${data.id}">Ответить</a></div>
            </div>
        `;

        if (parentId !== "0") {
            const parent = list.querySelector(`.comment[data-id="${parentId}"]`);
            parent.after(div);
        } else {
            list.appendChild(div);
        }

        input.value = '';
        form.querySelector("input[name=parent_id]").value = "0";
        input.placeholder = "Написать комментарий...";

        // ✅ Обновляем счётчик комментариев
        const postDiv = form.closest(".post");
        const counter = postDiv.querySelector(".count-number");
        if (counter) {
            counter.textContent = parseInt(counter.textContent) + 1;
        }
    });

    return false;
}


function renderComment(c, level = 0) {

    if (document.querySelector(`.comment[data-id="${c.id}"]`)) {
        return null; // уже есть
    }

    const div = document.createElement("div");
    div.className = "comment";
    div.dataset.id = c.id;
    div.style.marginLeft = (level * 25) + "px";

    // Определяем opacity
    const likeOpacity = c.user_reaction === "like" ? 1 : 0.5;
    const dislikeOpacity = c.user_reaction === "dislike" ? 1 : 0.5;

    div.innerHTML = `
        <img src="avatars/${c.avatar}" class="comment-avatar">

        <div class="comment-body">
            <b>${c.username}</b>: ${c.content}

            <div class="comment-date" data-time="${c.created_at.replace(' ', 'T')}Z"
                 style="color:#777; font-size:12px;">
            </div>

            <div class="comment-reactions" style="margin-top:5px;">

                <span style="cursor:pointer;"
                      onclick="likeComment(${c.id}, this)">
                    <img src="assets/like.svg" class="comment-like-icon"
                         style="width:16px; opacity:${likeOpacity};">
                    <span class="comment-like-count">${c.likes}</span>
                </span>

                <span style="cursor:pointer; margin-left:10px;"
                      onclick="dislikeComment(${c.id}, this)">
                    <img src="assets/dislike.svg" class="comment-dislike-icon"
                         style="width:16px; opacity:${dislikeOpacity};">
                    <span class="comment-dislike-count">${c.dislikes}</span>
                </span>

            </div>

            <div><a href="#" class="reply-link" data-id="${c.id}">Ответить</a></div>
        </div>
    `;

    // рендер детей
    if (c.children) {
        Object.values(c.children).forEach(child => {
            const childDiv = renderComment(child, level + 1);
            if (childDiv) div.appendChild(childDiv);
        });
    }

    return div;
}


function loadMoreComments(postId, btn) {
    const box = btn.parentNode;
    let page = parseInt(box.dataset.page) + 1;

    fetch(`comments.php?post_id=${postId}&page=${page}`)
        .then(r => r.json())
        .then(data => {
            const list = box.parentNode.querySelector('.comment-list');

            // добавляем только новые корневые комментарии и их ответы
            Object.values(data.comments).forEach(c => {
                const div = renderComment(c);
                list.appendChild(div);
            });

            // обновляем время
            document.querySelectorAll(".comment-date").forEach(el => {
                const date = new Date(el.dataset.time);
                el.textContent = timeAgo(date);
            });

            box.dataset.page = page;

            if (page >= data.total_pages) btn.remove();
        });
}

document.addEventListener("click", e => {
    if (e.target.classList.contains("show-replies")) {
        const id = e.target.dataset.id;

        const block = document.querySelector(`.replies-block[data-parent="${id}"]`);

        if (!block) return;

        if (block.style.display === "none") {
            block.style.display = "block";
            e.target.textContent = "Скрыть ответы";
        } else {
            block.style.display = "none";
            e.target.textContent = `Показать ответы (${block.children.length})`;
        }
    }
});

function likeComment(id, el) {
    fetch("comment_like.php?id=" + id)
        .then(r => r.text())
        .then(t => {
            let likeCount = el.querySelector(".comment-like-count");
            let likeIcon = el.querySelector(".comment-like-icon");

            let parent = el.parentNode;
            let dislikeArea = parent.querySelector(".comment-dislike-count");
            let dislikeIcon = parent.querySelector(".comment-dislike-icon");

            if (t === "LIKED") {
                likeIcon.style.opacity = "1";
                likeCount.textContent = +likeCount.textContent + 1;
            }

            if (t === "UNLIKED") {
                likeIcon.style.opacity = "0.5";
                likeCount.textContent = +likeCount.textContent - 1;
            }

            if (t === "LIKED_REMOVED_DISLIKE") {
                likeIcon.style.opacity = "1";
                likeCount.textContent = +likeCount.textContent + 1;

                dislikeIcon.style.opacity = "0.5";
                dislikeArea.textContent = +dislikeArea.textContent - 1;
            }

            if (t === "NOT_LOGGED_IN") {
                alert("Войдите, чтобы ставить лайки");
            }
        });
}


function dislikeComment(id, el) {
    fetch("comment_dislike.php?id=" + id)
        .then(r => r.text())
        .then(t => {
            let dislikeCount = el.querySelector(".comment-dislike-count");
            let dislikeIcon = el.querySelector(".comment-dislike-icon");

            let parent = el.parentNode;
            let likeArea = parent.querySelector(".comment-like-count");
            let likeIcon = parent.querySelector(".comment-like-icon");

            if (t === "DISLIKED") {
                dislikeIcon.style.opacity = "1";
                dislikeCount.textContent = +dislikeCount.textContent + 1;
            }

            if (t === "UNDISLIKED") {
                dislikeIcon.style.opacity = "0.5";
                dislikeCount.textContent = +dislikeCount.textContent - 1;
            }

            if (t === "DISLIKED_REMOVED_LIKE") {
                dislikeIcon.style.opacity = "1";
                dislikeCount.textContent = +dislikeCount.textContent + 1;

                likeIcon.style.opacity = "0.5";
                likeArea.textContent = +likeArea.textContent - 1;
            }

            if (t === "NOT_LOGGED_IN") {
                alert("Войдите, чтобы ставить дизлайки");
            }
        });
}

