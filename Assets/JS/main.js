const STORAGE_KEY = "kumo-clouds";

let clouds = loadClouds();
let activeCloud = null;

const cloudTree = document.getElementById("cloud-tree");
const cloudPreviewGrid = document.getElementById("cloud-preview-grid");
const emptyCloudState = document.getElementById("empty-cloud-state");

const cloudModal = document.getElementById("cloud-modal");
const categoryModal = document.getElementById("category-modal");

const cloudNameInput = document.getElementById("cloud-name");

const createButtons = [
  document.getElementById("create-cloud-sidebar"),
  document.getElementById("empty-create-cloud"),
].filter(Boolean);

const categoryOptions = document.querySelectorAll(".category-option");

function loadClouds() {
  const saved = localStorage.getItem(STORAGE_KEY);

  if (!saved) {
    return [];
  }

  try {
    return JSON.parse(saved);
  } catch {
    return [];
  }
}

function saveClouds() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(clouds));
}

function openCloudModal() {
  cloudNameInput.value = "";
  cloudModal.classList.add("open");

  setTimeout(() => {
    cloudNameInput.focus();
  }, 50);
}

function closeCloudModal() {
  cloudModal.classList.remove("open");
}

function openCategoryModal(cloud) {
  activeCloud = cloud;

  categoryOptions.forEach((option) => {
    const category = option.dataset.category;

    option.classList.toggle("selected", cloud.categories.includes(category));
  });

  categoryModal.classList.add("open");
}

function closeCategoryModal() {
  categoryModal.classList.remove("open");
  activeCloud = null;
}

function createCloud() {
  const name = cloudNameInput.value.trim();

  if (!name) {
    cloudNameInput.focus();
    return;
  }

  const cloud = {
    id: crypto.randomUUID(),
    name: name,
    categories: [],
    children: [],
  };

  clouds.push(cloud);

  saveClouds();
  closeCloudModal();
  render();

  openCategoryModal(cloud);
}

function toggleCategory(option) {
  if (!activeCloud) {
    return;
  }

  const category = option.dataset.category;

  if (activeCloud.categories.includes(category)) {
    activeCloud.categories = activeCloud.categories.filter(
      (item) => item !== category,
    );

    option.classList.remove("selected");
  } else {
    activeCloud.categories.push(category);
    option.classList.add("selected");
  }
}

function saveCategories() {
  if (!activeCloud) {
    return;
  }

  saveClouds();
  closeCategoryModal();
  render();
}

function renameCloud(id) {
  const cloud = findCloud(clouds, id);

  if (!cloud) {
    return;
  }

  const name = prompt("Rename cloud:", cloud.name);

  if (!name || !name.trim()) {
    return;
  }

  cloud.name = name.trim();

  saveClouds();
  render();
}

function deleteCloud(id) {
  const cloud = findCloud(clouds, id);

  if (!cloud) {
    return;
  }

  const confirmed = confirm(`Delete "${cloud.name}"?`);

  if (!confirmed) {
    return;
  }

  removeCloud(clouds, id);

  saveClouds();
  render();
}

function addSubCloud(parentId) {
  const parent = findCloud(clouds, parentId);

  if (!parent) {
    return;
  }

  const name = prompt(`Add a sub-cloud inside "${parent.name}":`);

  if (!name || !name.trim()) {
    return;
  }

  if (!parent.children) {
    parent.children = [];
  }

  parent.children.push({
    id: crypto.randomUUID(),
    name: name.trim(),
    categories: [],
    children: [],
  });

  saveClouds();
  render();
}

function createCloudElement(cloud) {
  const row = document.createElement("div");
  row.className = "cloud-row";

  const details = document.createElement("details");

  const summary = document.createElement("summary");
  summary.className = "cloud-summary";

  const arrow = document.createElement("span");
  arrow.className = "cloud-arrow";
  arrow.textContent = "›";

  const icon = document.createElement("span");
  icon.className = "icon";
  icon.textContent = "☁";

  const name = document.createElement("span");
  name.textContent = cloud.name;

  summary.append(arrow, icon, name);
  details.appendChild(summary);

  if (cloud.children && cloud.children.length > 0) {
    const children = document.createElement("div");
    children.className = "cloud-children";

    cloud.children.forEach((child) => {
      children.appendChild(createCloudElement(child));
    });

    details.appendChild(children);
  }

  const actions = document.createElement("div");
  actions.className = "cloud-actions";

  const add = document.createElement("button");
  add.className = "cloud-action";
  add.textContent = "+";
  add.title = "Add sub-cloud";

  add.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();

    addSubCloud(cloud.id);
  });

  const rename = document.createElement("button");
  rename.className = "cloud-action";
  rename.textContent = "✎";
  rename.title = "Rename";

  rename.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();

    renameCloud(cloud.id);
  });

  const remove = document.createElement("button");
  remove.className = "cloud-action";
  remove.textContent = "×";
  remove.title = "Delete";

  remove.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();

    deleteCloud(cloud.id);
  });

  actions.append(add, rename, remove);

  row.append(details, actions);

  return row;
}

function renderSidebar() {
  if (!cloudTree) {
    return;
  }

  cloudTree.innerHTML = "";

  clouds.forEach((cloud) => {
    cloudTree.appendChild(createCloudElement(cloud));
  });
}

function renderDashboard() {
  if (!cloudPreviewGrid || !emptyCloudState) {
    return;
  }

  cloudPreviewGrid.innerHTML = "";

  if (clouds.length === 0) {
    emptyCloudState.style.display = "flex";
    return;
  }

  emptyCloudState.style.display = "none";

  clouds.forEach((cloud) => {
    const card = document.createElement("div");
    card.className = "cloud-preview";

    const icon = document.createElement("div");
    icon.className = "cloud-preview-icon";
    icon.textContent = "☁";

    const title = document.createElement("strong");
    title.textContent = cloud.name;

    const info = document.createElement("small");

    const categoryCount = cloud.categories?.length || 0;

    if (categoryCount === 0) {
      info.textContent = "No categories yet";
    } else if (categoryCount === 1) {
      info.textContent = "1 category";
    } else {
      info.textContent = `${categoryCount} categories`;
    }

    card.append(icon, title, info);

    card.addEventListener("click", () => {
      openCategoryModal(cloud);
    });

    cloudPreviewGrid.appendChild(card);
  });
}

function findCloud(list, id) {
  for (const cloud of list) {
    if (cloud.id === id) {
      return cloud;
    }

    if (cloud.children?.length) {
      const found = findCloud(cloud.children, id);

      if (found) {
        return found;
      }
    }
  }

  return null;
}

function removeCloud(list, id) {
  const index = list.findIndex((cloud) => cloud.id === id);

  if (index !== -1) {
    list.splice(index, 1);
    return true;
  }

  for (const cloud of list) {
    if (cloud.children?.length) {
      if (removeCloud(cloud.children, id)) {
        return true;
      }
    }
  }

  return false;
}

function render() {
  renderSidebar();
  renderDashboard();
}

function closeAllModals() {
  closeCloudModal();
  closeCategoryModal();
}

function initialise() {
  createButtons.forEach((button) => {
    button.addEventListener("click", openCloudModal);
  });

  const createConfirm = document.getElementById("create-cloud-confirm");

  if (createConfirm) {
    createConfirm.addEventListener("click", createCloud);
  }

  const closeCloud = document.getElementById("close-cloud-modal");

  if (closeCloud) {
    closeCloud.addEventListener("click", closeCloudModal);
  }

  const closeCategory = document.getElementById("close-category-modal");

  if (closeCategory) {
    closeCategory.addEventListener("click", closeCategoryModal);
  }

  const saveCategoryButton = document.getElementById("save-categories");

  if (saveCategoryButton) {
    saveCategoryButton.addEventListener("click", saveCategories);
  }

  categoryOptions.forEach((option) => {
    option.addEventListener("click", () => toggleCategory(option));
  });

  cloudNameInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      createCloud();
    }
  });

  cloudModal.addEventListener("click", (event) => {
    if (event.target === cloudModal) {
      closeCloudModal();
    }
  });

  categoryModal.addEventListener("click", (event) => {
    if (event.target === categoryModal) {
      closeCategoryModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();

      const searchInput = document.getElementById("search-input");

      if (searchInput) {
        searchInput.focus();
      }
    }

    if (event.key === "Escape") {
      closeAllModals();
    }
  });

  render();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initialise);
} else {
  initialise();
}
