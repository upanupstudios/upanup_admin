document.addEventListener('DOMContentLoaded', () => {
  const secondaryEdit = document.querySelector('.cau-node-edit__secondary');
  if (secondaryEdit && secondaryEdit.querySelector('[required]')) {
    secondaryEdit.setAttribute('open', '');
  }
});