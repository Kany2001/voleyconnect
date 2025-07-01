document.addEventListener('DOMContentLoaded', function() {
    // --- Lógica para Likes ---
    const likeButtons = document.querySelectorAll('.like-button');

    likeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.id;
            const likesCountSpan = document.getElementById('likes-' + postId);

            fetch('like_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'id_publicacion=' + postId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    likesCountSpan.textContent = data.total_likes;
                    if (data.action === 'liked') {
                        button.classList.add('liked');
                    } else {
                        button.classList.remove('liked');
                    }
                } else {
                    console.error('Error al dar/quitar Me gusta:', data.message);
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error en la petición de like:', error);
                alert('Hubo un problema al procesar tu solicitud de Me gusta. Inténtalo de nuevo.');
            });
        });
    });

    // --- Lógica para Comentarios (AJAX) ---
    const commentForms = document.querySelectorAll('.comment-form');

    commentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const postId = this.dataset.postId;
            const contenidoComentario = this.querySelector('textarea[name="contenido"]').value.trim(); // Usa 'contenido'
            const commentsList = document.getElementById('comments-' + postId);
            const commentsCountSpan = document.getElementById('comments-count-' + postId);
            const noCommentsMessage = commentsList.querySelector('.no-comments');

            if (contenidoComentario === '') {
                alert('El comentario no puede estar vacío.');
                return;
            }

            fetch('comment_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'id_publicacion=' + postId + '&contenido=' + encodeURIComponent(contenidoComentario) // Envía 'contenido'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    commentsList.innerHTML = ''; // Limpiar la lista de comentarios actual
                    if (noCommentsMessage) {
                        noCommentsMessage.style.display = 'none'; 
                    }
                    if (data.comments.length > 0) {
                        data.comments.forEach(comment => {
                            const commentItem = `
                                <div class="comment-item">
                                    <img src="${comment.foto_perfil_url || 'img/default-avatar.png'}" alt="Perfil" class="comment-profile-pic rounded-circle me-2">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold me-2">${comment.nombre_usuario}</span>
                                            <small class="text-muted" style="font-size: 0.7em;">${new Date(comment.fecha_comentario).toLocaleString()}</small>
                                        </div>
                                        <p class="mb-0">${comment.contenido}</p>
                                    </div>
                                </div>
                            `;
                            commentsList.innerHTML += commentItem;
                        });
                    } else {
                        commentsList.innerHTML = `<p class="no-comments text-center text-muted">Sé el primero en comentar esta publicación.</p>`;
                    }
                    commentsCountSpan.textContent = data.comments.length; 
                    this.querySelector('textarea[name="contenido"]').value = ''; // Limpiar el textarea
                } else {
                    throw new Error(data.message || 'Error al cargar los comentarios.');
                }
            })
            .catch(error => {
                console.error('Error en la petición de comentario:', error);
                alert('Hubo un problema al enviar/obtener tu comentario. Inténtalo de nuevo.');
            });
        });
    });

    // --- Lógica para validación del formulario de publicación ---
    const publicacionForm = document.querySelector('form[name="submit_publicacion_form"]'); 
    if (publicacionForm) {
        publicacionForm.addEventListener('submit', function(e) {
            const contenidoTexto = this.querySelector('textarea[name="contenido_texto"]').value.trim();
            const imagenPublicacion = this.querySelector('input[name="imagen_publicacion"]').files.length > 0;
            const urlVideo = this.querySelector('input[name="url_video"]').value.trim();

            if (contenidoTexto === "" && !imagenPublicacion && urlVideo === "") {
                alert("Debes escribir algo, subir una imagen o enlazar un video para tu publicación.");
                e.preventDefault(); 
            }
        });
    }
});