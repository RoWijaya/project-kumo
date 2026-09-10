function goToStep(currentStepId, nextStepId) {
  const currentStep = document.getElementById(currentStepId);
  const nextStep = document.getElementById(nextStepId);

  if (currentStep && nextStep) {
    currentStep.style.display = "none";
    nextStep.style.display = "block";
  } else {
    console.error(
      "Error: Could not find one of these IDs in your HTML:",
      currentStepId,
      nextStepId,
    );
  }
}

(function () {
  "use strict";

  function openModal(overlay) {
    if (!overlay) return;
    overlay.classList.add("active");
    document.body.style.overflow = "hidden";
  }

  function closeModal(overlay) {
    if (!overlay) return;
    overlay.classList.remove("active");
    document.body.style.overflow = "";
  }

  document.addEventListener("click", function (e) {
    if (e.target.classList && e.target.classList.contains("modal-overlay")) {
      closeModal(e.target);
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal-overlay.active").forEach(closeModal);
      hidePopover();
    }
  });

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  const onboardingOverlay = document.getElementById("onboarding-modal");
  const viewport = document.getElementById("onboarding-viewport");
  const progressEl = document.getElementById("onboarding-progress");

  const LIFE_STAGES = [
    { id: "university", label: "University", emoji: "🎓" },
    { id: "highschool", label: "High School", emoji: "🏫" },
    { id: "middleschool", label: "Middle School", emoji: "📘" },
    { id: "elementary", label: "Elementary", emoji: "✏️" },
    { id: "work", label: "Work", emoji: "💼" },
  ];

  const FLOW = {
    university: ["semester", "classes"],
    highschool: ["grade", "subjects"],
    middleschool: ["grade", "subjects"],
    elementary: ["grade", "subjects"],
    work: ["role", "responsibilities"],
  };

  let ob = {
    stage: null,
    path: [],
    index: 0,
    answers: { semester: "", grade: "", role: "", list: [""] },
  };

  function resetOnboarding() {
    ob = {
      stage: null,
      path: ["life-stage"],
      index: 0,
      answers: { semester: "", grade: "", role: "", list: [""] },
    };
  }

  function currentStepName() {
    return ob.path[ob.index];
  }

  function renderProgress() {
    const total = ob.path.length;
    progressEl.innerHTML = ob.path
      .map((_, i) => {
        let cls = "progress-dot";
        if (i === ob.index) cls += " active";
        else if (i < ob.index) cls += " done";
        return `<span class="${cls}"></span>`;
      })
      .join("");
    if (total <= 1) progressEl.style.display = "none";
    else progressEl.style.display = "flex";
  }

  function stepTemplate(name) {
    switch (name) {
      case "life-stage":
        return `
          <div class="onboarding-step" data-step="life-stage">
            <div class="step-icon">☁</div>
            <h2>What are you focusing on right now?</h2>
            <p class="step-subtitle">This helps Kumo set up your first Clouds.</p>
            <div class="stage-grid">
              ${LIFE_STAGES.map(
                (s) => `
                <button class="stage-option" data-stage="${s.id}">
                  <span class="stage-emoji">${s.emoji}</span>
                  <strong>${s.label}</strong>
                </button>`,
              ).join("")}
            </div>
            <button class="skip-setup" id="skip-onboarding">Customize on my own →</button>
          </div>`;

      case "semester":
        return textStep({
          icon: "🎓",
          title: "What semester are you in?",
          subtitle: "e.g. Fall 2026, Sophomore Spring...",
          placeholder: "Type your semester",
          field: "semester",
        });

      case "grade":
        return textStep({
          icon: "📘",
          title: "What grade and semester are you in?",
          subtitle: "e.g. 10th Grade, Fall term",
          placeholder: "Type your grade and semester",
          field: "grade",
        });

      case "role":
        return textStep({
          icon: "💼",
          title: "What is your role or primary project?",
          subtitle: "e.g. Product Designer, Freelance branding client",
          placeholder: "Type your role or project",
          field: "role",
        });

      case "classes":
        return listStep({
          icon: "📚",
          title: "What classes are you taking?",
          subtitle: "Add each class — you can always change these later.",
          placeholder: "e.g. Organic Chemistry",
        });

      case "subjects":
        return listStep({
          icon: "📚",
          title: "What subjects are you taking?",
          subtitle: "Add each subject — you can always change these later.",
          placeholder: "e.g. Algebra II",
        });

      case "responsibilities":
        return listStep({
          icon: "🗂",
          title: "What are your main responsibilities or sub-projects?",
          subtitle: "Add 2–3 things that make up your day-to-day.",
          placeholder: "e.g. Client onboarding",
        });

      case "complete":
        return completeTemplate();

      default:
        return "";
    }
  }

  function textStep({ icon, title, subtitle, placeholder, field }) {
    return `
      <div class="onboarding-step" data-step="text">
        <div class="step-icon">${icon}</div>
        <h2>${title}</h2>
        <p class="step-subtitle">${subtitle}</p>
        <input type="text" id="ob-text-input" placeholder="${placeholder}" value="${escapeHtml(
          ob.answers[field] || "",
        )}" />
        <div class="onboarding-nav" style="margin-top:16px;">
          <button class="nav-back" id="ob-back">Back</button>
          <button class="primary-button" id="ob-next" data-field="${field}">Continue</button>
        </div>
      </div>`;
  }

  function listStep({ icon, title, subtitle, placeholder }) {
    const rows = ob.answers.list
      .map(
        (val, i) => `
        <div class="repeatable-row" data-row="${i}">
          <input type="text" class="list-input" placeholder="${placeholder}" value="${escapeHtml(
            val,
          )}" />
          <button class="remove-row" data-remove="${i}" aria-label="Remove">×</button>
        </div>`,
      )
      .join("");

    return `
      <div class="onboarding-step" data-step="list">
        <div class="step-icon">${icon}</div>
        <h2>${title}</h2>
        <p class="step-subtitle">${subtitle}</p>
        <div class="repeatable-list" id="ob-list">${rows}</div>
        <button class="add-row-btn" id="ob-add-row">+ Add another</button>
        <div class="onboarding-nav">
          <button class="nav-back" id="ob-back">Back</button>
          <button class="primary-button" id="ob-next">We've arranged your Sky</button>
        </div>
      </div>`;
  }

  function completeTemplate() {
    const stageLabel =
      LIFE_STAGES.find((s) => s.id === ob.stage)?.label || "Your Sky";
    const items = ob.answers.list.filter((v) => v.trim() !== "");
    return `
      <div class="onboarding-step complete-step" data-step="complete">
        <div class="step-icon">✦</div>
        <h2>We've arranged your Sky</h2>
        <p class="step-subtitle">Here's the Cloud we've set up for you.</p>
        <div class="sky-result-card">
          <span class="result-icon">☁</span>
          <div>
            <strong>${escapeHtml(stageLabel)}</strong>
            <ul>
              ${items.map((i) => `<li>${escapeHtml(i)}</li>`).join("")}
              ${items.length === 0 ? "<li>Ready whenever you are</li>" : ""}
            </ul>
          </div>
        </div>
        <button class="primary-button" id="ob-finish">Enter your Sky</button>
      </div>`;
  }

  function renderStep(direction) {
    renderProgress();
    const html = stepTemplate(currentStepName());
    const outgoing = viewport.querySelector(".onboarding-step");

    function mountNew() {
      viewport.innerHTML = html;
      const incoming = viewport.querySelector(".onboarding-step");
      incoming.classList.add(
        direction === "back" ? "slide-in-left" : "slide-in-right",
      );
      bindStepEvents();
    }

    if (outgoing) {
      outgoing.classList.add(
        direction === "back" ? "slide-out-right" : "slide-out-left",
      );
      outgoing.addEventListener("animationend", mountNew, { once: true });
    } else {
      mountNew();
    }
  }

  function goNext() {
    if (ob.index < ob.path.length - 1) {
      ob.index++;
      renderStep("forward");
    }
  }

  function goBack() {
    if (ob.index > 0) {
      ob.answers.list = ob.answers.list.length ? ob.answers.list : [""];
      ob.index--;
      renderStep("back");
    }
  }

  function bindStepEvents() {
    const step = currentStepName();

    if (step === "life-stage") {
      viewport.querySelectorAll(".stage-option").forEach((btn) => {
        btn.addEventListener("click", () => {
          ob.stage = btn.dataset.stage;
          ob.path = ["life-stage", ...FLOW[ob.stage], "complete"];
          ob.answers.list = [""];
          goNext();
        });
      });
      const skip = document.getElementById("skip-onboarding");
      if (skip) skip.addEventListener("click", finishOnboarding);
    }

    if (step === "text") {
      const input = document.getElementById("ob-text-input");
      const nextBtn = document.getElementById("ob-next");
      const backBtn = document.getElementById("ob-back");
      input.focus();
      nextBtn.addEventListener("click", () => {
        ob.answers[nextBtn.dataset.field] = input.value.trim();
        goNext();
      });
      input.addEventListener("keydown", (e) => {
        if (e.key === "Enter") nextBtn.click();
      });
      backBtn.addEventListener("click", goBack);
    }

    if (step === "list") {
      const listEl = document.getElementById("ob-list");
      const addBtn = document.getElementById("ob-add-row");
      const nextBtn = document.getElementById("ob-next");
      const backBtn = document.getElementById("ob-back");

      function syncFromDOM() {
        ob.answers.list = Array.from(
          listEl.querySelectorAll(".list-input"),
        ).map((i) => i.value);
      }

      addBtn.addEventListener("click", () => {
        syncFromDOM();
        ob.answers.list.push("");
        renderStep("forward-inline");
      });

      listEl.addEventListener("click", (e) => {
        const btn = e.target.closest(".remove-row");
        if (!btn) return;
        syncFromDOM();
        const idx = Number(btn.dataset.remove);
        if (ob.answers.list.length > 1) ob.answers.list.splice(idx, 1);
        renderStep("forward-inline");
      });

      nextBtn.addEventListener("click", () => {
        syncFromDOM();
        goNext();
      });
      backBtn.addEventListener("click", () => {
        syncFromDOM();
        goBack();
      });
    }

    if (step === "complete") {
      document
        .getElementById("ob-finish")
        .addEventListener("click", finishOnboarding);
    }
  }

  function finishOnboarding() {
    try {
      localStorage.setItem("kumoOnboardingComplete", "1");
    } catch (e) {}
    closeModal(onboardingOverlay);
  }

  document
    .getElementById("close-onboarding-modal")
    .addEventListener("click", finishOnboarding);

  function startOnboarding() {
    resetOnboarding();
    openModal(onboardingOverlay);
    renderStep("forward");
  }

  let alreadyOnboarded = false;
  try {
    alreadyOnboarded = localStorage.getItem("kumoOnboardingComplete") === "1";
  } catch (e) {}
  if (!alreadyOnboarded) {
    startOnboarding();
  }

  const arrangeBtn = document.querySelector(".arrange");

  const settingsOverlay = document.getElementById("settings-modal");
  const settingsBtn = document.querySelector(".settings");

  if (settingsBtn) {
    settingsBtn.addEventListener("click", () => openModal(settingsOverlay));
  }
  document
    .getElementById("close-settings-modal")
    .addEventListener("click", () => closeModal(settingsOverlay));

  document.querySelectorAll(".settings-tab").forEach((tab) => {
    tab.addEventListener("click", () => {
      document
        .querySelectorAll(".settings-tab")
        .forEach((t) => t.classList.remove("active"));
      document
        .querySelectorAll(".settings-panel")
        .forEach((p) => p.classList.remove("active"));
      tab.classList.add("active");
      document
        .querySelector(`.settings-panel[data-panel="${tab.dataset.tab}"]`)
        .classList.add("active");
    });
  });

  const skyToggle = document.getElementById("sky-mode-toggle");
  const skyLabel = document.getElementById("sky-mode-label");

  function applySkyMode(isNight) {
    document.body.classList.toggle("night-sky", isNight);
    skyLabel.textContent = isNight ? "Night Sky" : "Day Sky";
  }

  let savedNight = false;
  try {
    savedNight = localStorage.getItem("kumoNightSky") === "1";
  } catch (e) {
    /* ignore */
  }
  skyToggle.checked = savedNight;
  applySkyMode(savedNight);

  skyToggle.addEventListener("change", () => {
    applySkyMode(skyToggle.checked);
    try {
      localStorage.setItem("kumoNightSky", skyToggle.checked ? "1" : "0");
    } catch (e) {
      /* ignore */
    }
  });

  document.querySelectorAll(".settings-save").forEach((btn) => {
    btn.addEventListener("click", () => {
      // Hook this up to your PHP save endpoint (e.g. fetch POST to
      // Assets/backend/update-account.php) — placeholder feedback below.
      const original = btn.textContent;
      btn.textContent = "Saved ✓";
      setTimeout(() => (btn.textContent = original), 1400);
    });
  });

  const cloudModal = document.getElementById("cloud-modal");

  function openCloudModal() {
    openModal(cloudModal);
    const nameInput = document.getElementById("cloud-name");
    if (nameInput) setTimeout(() => nameInput.focus(), 50);
  }

  ["create-cloud-sidebar", "empty-create-cloud"].forEach((id) => {
    const trigger = document.getElementById(id);
    if (trigger) trigger.addEventListener("click", openCloudModal);
  });

  document
    .getElementById("close-cloud-modal")
    .addEventListener("click", () => closeModal(cloudModal));

  document
    .getElementById("create-cloud-confirm")
    .addEventListener("click", () => {
      const name = document.getElementById("cloud-name").value.trim();
      const location = document.getElementById("cloud-location").value;
      if (!name) return;
      // Hook this up to your PHP create-cloud endpoint here, passing
      // { name, location }. Placeholder: just close the modal.
      closeModal(cloudModal);
      document.getElementById("cloud-name").value = "";
    });

  const popoverEl = document.getElementById("info-popover-template");
  const popoverTitle = document.getElementById("info-popover-title");
  const popoverBody = document.getElementById("info-popover-body");
  let popoverOwner = null;

  function showPopover(trigger, title, body) {
    if (popoverOwner === trigger && popoverEl.classList.contains("visible")) {
      hidePopover();
      return;
    }
    popoverTitle.textContent = title;
    popoverBody.textContent = body;
    popoverEl.classList.add("visible");
    popoverOwner = trigger;

    const rect = trigger.getBoundingClientRect();
    const popRect = popoverEl.getBoundingClientRect();
    let top = rect.bottom + 10;
    let left = rect.left;

    if (left + popRect.width > window.innerWidth - 16) {
      left = window.innerWidth - popRect.width - 16;
    }
    if (top + popRect.height > window.innerHeight - 16) {
      top = rect.top - popRect.height - 10;
    }
    popoverEl.style.top = `${top}px`;
    popoverEl.style.left = `${left}px`;
  }

  function hidePopover() {
    popoverEl.classList.remove("visible");
    popoverOwner = null;
  }

  document.addEventListener("click", (e) => {
    if (
      popoverOwner &&
      !popoverEl.contains(e.target) &&
      e.target !== popoverOwner &&
      !popoverOwner.contains(e.target)
    ) {
      hidePopover();
    }
  });

  function attachInfoPopover(trigger, title, body) {
    if (!trigger) return;
    trigger.addEventListener("click", (e) => {
      e.stopPropagation();
      showPopover(trigger, title, body);
    });
  }

  attachInfoPopover(
    arrangeBtn,
    "Arrange Your Sky",
    "Drag and drop your Clouds to reorganize your workspace.",
  );

  function goBack(currentStepId, previousStepId) {
    document.getElementById(currentStepId).style.display = "none";
    document.getElementById(previousStepId).style.display = "block";
  }

  document.addEventListener("DOMContentLoaded", () => {
    const btnUniversity = document.getElementById("btn-university");
    if (btnUniversity) {
      btnUniversity.addEventListener("click", () => {
        goToStep("step-1", "step-2-uni");
        updateProgressBars(2);
      });
    }

    const btnSkip = document.getElementById("btn-skip");
    if (btnSkip) {
      btnSkip.addEventListener("click", () => {
        closeModal();
      });
    }
  });
})();

class KumoTour {
  constructor() {
    this.currentStep = 0;
    this.steps = [
      {
        target: null,
        title: "Hi there! 👋",
        text: "Welcome to Kumo. Before we set up your first Clouds, let me quickly show you around your new peaceful workspace.",
        buttonText: "Show me around",
      },
      {
        target: "#create-cloud-sidebar",
        title: "Create your spaces",
        text: "This is where you'll build your Sky. You can create Clouds for your classes, projects, or personal life right here.",
        buttonText: "Next",
        position: "right",
      },
      {
        target: ".arrange",
        title: "Tidy up anytime",
        text: "Whenever your Sky gets a little crowded, use this to organize, archive, and arrange your layout perfectly.",
        buttonText: "Got it, let's start!",
      },
    ];
    this.init();
  }

  init() {
    this.overlay = document.createElement("div");
    this.overlay.className = "tour-overlay";
    document.body.appendChild(this.overlay);

    this.tooltip = document.createElement("div");
    this.tooltip.className = "tour-tooltip";
    this.tooltip.innerHTML = `
      <h3 id="tour-title"></h3>
      <p id="tour-text"></p>
      <div class="tour-actions">
        <button class="skip-btn" id="tour-skip">Skip tour</button>
        <button class="primary-button" id="tour-next" style="width: auto; padding: 10px 20px;"></button>
      </div>
    `;
    document.body.appendChild(this.tooltip);

    document
      .getElementById("tour-next")
      .addEventListener("click", () => this.nextStep());
    document
      .getElementById("tour-skip")
      .addEventListener("click", () => this.endTour());
  }

  start() {
    this.overlay.classList.add("active");
    this.showStep();
  }

  showStep() {
    const step = this.steps[this.currentStep];

    document
      .querySelectorAll(".tour-highlight")
      .forEach((el) => el.classList.remove("tour-highlight"));

    document.getElementById("tour-title").textContent = step.title;
    document.getElementById("tour-text").textContent = step.text;
    document.getElementById("tour-next").textContent = step.buttonText;

    if (step.target) {
      const targetEl = document.querySelector(step.target);
      if (targetEl) {
        targetEl.classList.add("tour-highlight");

        const rect = targetEl.getBoundingClientRect();
        let leftPosition = rect.left;

        if (step.position === "right") {
          this.tooltip.style.top = `${rect.top}px`;
          leftPosition = rect.right + 20;
        } else {
          this.tooltip.style.top = `${rect.bottom + 20}px`;
        }

        if (leftPosition + 360 > window.innerWidth) {
          leftPosition = window.innerWidth - 360;
        }

        this.tooltip.style.left = `${leftPosition}px`;
        this.tooltip.style.transform = `translateY(0)`;
      }
    } else {
      this.tooltip.style.top = "50%";
      this.tooltip.style.left = "50%";
      this.tooltip.style.transform = "translate(-50%, -50%)";
    }

    this.tooltip.classList.add("active");
  }

  nextStep() {
    this.currentStep++;
    if (this.currentStep >= this.steps.length) {
      this.endTour();
    } else {
      this.showStep();
    }
  }

  endTour() {
    this.overlay.classList.remove("active");
    this.tooltip.classList.remove("active");
    document
      .querySelectorAll(".tour-highlight")
      .forEach((el) => el.classList.remove("tour-highlight"));

    setTimeout(() => {
      const onboardingModal = document.getElementById("onboarding-modal");
      if (onboardingModal) {
        onboardingModal.style.display = "flex";

        setTimeout(() => onboardingModal.classList.add("active"), 10);
      }
    }, 300);
  }
}

async function finishSetup() {
  const semesterInput = document.getElementById("uni-semester").value.trim();
  const classesRaw = document.getElementById("uni-classes").value.trim();
  if (!semesterInput || !classesRaw) {
    alert("Please fill in your semester and classes.");
    return;
  }
  sendSetupData(semesterInput, classesRaw);
}

async function finishSetupHS() {
  const gradeInput = document.getElementById("hs-grade").value.trim();
  const classesRaw = document.getElementById("hs-classes").value.trim();
  if (!gradeInput || !classesRaw) {
    alert("Please fill in your grade and subjects.");
    return;
  }
  sendSetupData(gradeInput, classesRaw);
}

async function finishSetupMS() {
  const gradeInput = document.getElementById("ms-grade").value.trim();
  const classesRaw = document.getElementById("ms-classes").value.trim();
  if (!gradeInput || !classesRaw) {
    alert("Please fill in your grade and subjects.");
    return;
  }
  sendSetupData(gradeInput, classesRaw);
}

async function finishSetupElem() {
  const gradeInput = document.getElementById("elem-grade").value.trim();
  const classesRaw = document.getElementById("elem-classes").value.trim();
  if (!gradeInput || !classesRaw) {
    alert("Please fill in your grade and subjects.");
    return;
  }
  sendSetupData(gradeInput, classesRaw);
}

async function finishSetupWork() {
  const titleInput = document.getElementById("work-title").value.trim();
  const projectsRaw = document.getElementById("work-projects").value.trim();
  if (!titleInput || !projectsRaw) {
    alert("Please fill in your title and projects.");
    return;
  }
  sendSetupData(titleInput, projectsRaw);
}

async function sendSetupData(mainCategory, subItemsRaw) {
  const subItemsArray = subItemsRaw
    .split(",")
    .map((item) => item.trim())
    .filter((item) => item !== "");

  const payload = {
    stage: "university",
    semester: mainCategory,
    classes: subItemsArray,
  };

  try {
    const response = await fetch("../Assets/backend/setup_sky.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const result = await response.json();

    if (result.success) {
      const currentStep =
        document.querySelector('.onboarding-step[style*="display: flex"]') ||
        document.querySelector('.onboarding-step[style*="display: block"]');
      if (currentStep) currentStep.style.display = "none";

      document.getElementById("step-success").style.display = "flex";
    } else {
      console.error("Server Error:", result.error);
    }
  } catch (error) {
    console.error("Network Error:", error);
  }
}
