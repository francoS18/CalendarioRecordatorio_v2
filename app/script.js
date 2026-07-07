document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('appModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalConfirm = document.getElementById('modalConfirm');
    const modalCancel = document.getElementById('modalCancel');
    const modalClose = document.getElementById('modalClose');

    let confirmAction = null;

    const openModal = ({ title, message, confirmText = 'Aceptar', cancelText = '', danger = false, onConfirm = null }) => {
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modalConfirm.textContent = confirmText;
        modalCancel.textContent = cancelText;
        confirmAction = onConfirm;

        modalConfirm.classList.toggle('btn-danger', danger);
        modalConfirm.classList.toggle('btn', true);
        modalCancel.style.display = cancelText ? 'inline-flex' : 'none';
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        modalConfirm.focus();
    };

    const closeModal = () => {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        confirmAction = null;
    };

    document.querySelectorAll('.delete-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const taskTitle = link.dataset.title || 'esta tarea';

            openModal({
                title: 'Confirmar eliminación',
                message: `¿Seguro que deseas borrar "${taskTitle}"? Esta acción no se puede deshacer.`,
                confirmText: 'Sí, borrar',
                cancelText: 'Cancelar',
                danger: true,
                onConfirm: () => {
                    window.location.href = link.href;
                },
            });
        });
    });

    modalConfirm.addEventListener('click', () => {
        if (typeof confirmAction === 'function') {
            confirmAction();
        } else {
            closeModal();
        }
    });

    modalCancel.addEventListener('click', closeModal);
    modalClose.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    if (window.flashMessage && window.flashMessage.message) {
        openModal({
            title: window.flashMessage.type === 'success' ? 'Operación exitosa' : 'Aviso',
            message: window.flashMessage.message,
            confirmText: 'Aceptar',
        });
    }
});
