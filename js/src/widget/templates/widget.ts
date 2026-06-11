import { type Controller, el, toInstance } from '../controller';
import type { WidgetInstance } from '../types';

/**
 * Floating-button shell: a round toggle that opens a modal containing the
 * dropzone, status, upload queue and (when listing is enabled) a collapsible
 * file list. The collapse/toggle chrome is widget-local; the file data and the
 * table itself come from the shared controller.
 *
 * Preserves the historical class contract: `.fu-widget`, `.fu-toggle`,
 * `.fu-modal`(+`.fu-open`), `.fu-header`, `.fu-title`, `.fu-close`, `.fu-table`.
 */
export function mountWidgetTemplate(controller: Controller): WidgetInstance {
  const { config, strings, root, parts } = controller;
  const { dropzone, status, queueContainer, listContainer, refreshButton } = parts;

  // `.fu-widget` carries the floating-button position chrome; add (don't clobber)
  // so a pre-existing container class list and the theme class survive.
  root.classList.add('fu-widget');

  const toggle = el('button', 'fu-toggle', { type: 'button' });
  toggle.textContent = strings.toggle;

  const modal = el('div', 'fu-modal');
  const header = el('div', 'fu-header');
  const title = el('span', 'fu-title');
  title.textContent = strings.title;
  const close = el('button', 'fu-close', { type: 'button' });
  close.innerHTML = '&times;';
  header.append(title, close);

  modal.append(header, dropzone, status, queueContainer);

  if (config.allowList && listContainer) {
    const listActions = el('div', 'fu-list-actions');
    const toggleListBtn = el('button', 'fu-pill secondary', { type: 'button' });
    toggleListBtn.textContent = strings.showList;
    listActions.append(toggleListBtn, refreshButton);
    modal.append(listActions, listContainer);

    let listOpen = false;
    toggleListBtn.addEventListener('click', () => {
      listOpen = !listOpen;
      listContainer.classList.toggle('fu-open', listOpen);
      toggleListBtn.textContent = listOpen ? strings.hideList : strings.showList;
      if (listOpen) {
        void controller.fetchFiles();
      }
    });
  }

  root.append(modal, toggle);
  if (!controller.hasContainer) {
    document.body.appendChild(root);
  }

  let isOpen = false;
  toggle.addEventListener('click', () => {
    isOpen = !isOpen;
    modal.classList.toggle('fu-open', isOpen);
  });
  close.addEventListener('click', () => {
    isOpen = false;
    modal.classList.toggle('fu-open', false);
  });

  // Bugfix: load the persisted list on mount so it is current the moment the
  // modal is opened (and survives a page refresh). Fire-and-forget; fetchFiles
  // swallows its own errors so this never rejects.
  if (config.allowList) {
    void controller.fetchFiles();
  }

  return toInstance(controller);
}
