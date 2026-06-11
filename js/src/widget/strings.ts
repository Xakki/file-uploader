export interface Strings {
  title: string;
  toggle: string;
  drop: string;
  select: string;
  uploading: string;
  completed: string;
  failed: string;
  files: string;
  refresh: string;
  delete: string;
  restore: string;
  close: string;
  showList: string;
  hideList: string;
  empty: string;
  size: string;
  created: string;
  actions: string;
  copy: string;
  copied: string;
  errorCopy: string;
  movedToTrash: string;
}

export const STRINGS: Record<string, Strings> = {
  en: {
    title: 'File uploader',
    toggle: '⇪',
    drop: 'Drop files here or click to browse',
    select: 'Select file',
    uploading: 'Uploading :name',
    completed: 'Upload finished',
    failed: 'Upload failed',
    files: 'Files',
    refresh: 'Refresh',
    delete: 'Delete',
    restore: 'Restore',
    close: 'Close',
    showList: 'Show files',
    hideList: 'Hide files',
    empty: 'No files yet',
    size: 'Size',
    created: 'Created',
    actions: 'Actions',
    copy: 'Copy link',
    copied: 'Link copied',
    errorCopy: 'Unable to copy',
    movedToTrash: 'File moved to trash',
  },
  ru: {
    title: 'Загрузчик файлов',
    toggle: '⇪',
    drop: 'Перетащите файлы сюда или нажмите для выбора',
    select: 'Выбрать файл',
    uploading: 'Загрузка «:name»',
    completed: 'Загрузка завершена',
    failed: 'Ошибка загрузки',
    files: 'Файлы',
    refresh: 'Обновить',
    delete: 'Удалить',
    restore: 'Восстановить',
    close: 'Закрыть',
    showList: 'Показать файлы',
    hideList: 'Скрыть файлы',
    empty: 'Пока нет файлов',
    size: 'Размер',
    created: 'Создан',
    actions: 'Действия',
    copy: 'Копировать ссылку',
    copied: 'Ссылка скопирована',
    errorCopy: 'Не удалось скопировать',
    movedToTrash: 'Файл перемещён в корзину',
  },
};
