const modal = document.getElementById('noteModal');
const openModal = () => { modal.hidden = false; modal.querySelector('input').focus(); };
document.getElementById('openNote').addEventListener('click', openModal);
document.getElementById('newNote').addEventListener('click', openModal);
modal.querySelector('.close-modal').addEventListener('click', () => modal.hidden = true);
modal.addEventListener('click', event => { if (event.target === modal) modal.hidden = true; });
document.addEventListener('keydown', event => { if (event.key === 'Escape') modal.hidden = true; });
document.querySelectorAll('.check').forEach(check => check.addEventListener('click', () => check.classList.toggle('done')));
document.getElementById('askAssistant').addEventListener('click', () => alert('Silver Lining is ready to help with your Operating Systems notes.'));
