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

  const modalReturnFocus = new WeakMap();

  // Edge navigation: hover reveals the desktop sidebar; touch/click pins it open.
  const appShell = document.getElementById("kumo-app");
  const sidebar = document.getElementById("kumo-sidebar");
  const sidebarPeek = document.getElementById("sidebar-peek");
  if (appShell && sidebar && sidebarPeek) {
    const mobileSidebar = () => window.matchMedia("(max-width: 840px)").matches;
    const setSidebarOpen = (open) => {
      sidebar.classList.toggle("sidebar-open", open);
      appShell.classList.toggle("sidebar-open", open);
      appShell.classList.toggle("sidebar-mobile-open", open && mobileSidebar());
      sidebarPeek.setAttribute("aria-expanded", String(open));
      sidebarPeek.setAttribute("aria-label", open ? "Close navigation" : "Open navigation");
      sidebarPeek.title = open ? "Close navigation" : "Open navigation";
      const icon = sidebarPeek.querySelector(".material-symbols-outlined");
      if (icon) icon.textContent = open ? "chevron_left" : "chevron_right";
    };
    sidebarPeek.addEventListener("click", (event) => {
      event.preventDefault();
      // Read state from appShell (the source of truth for CSS) to handle
      // the initial hardcoded "sidebar-open" class on the app shell element.
      setSidebarOpen(!appShell.classList.contains("sidebar-open"));
    });
    document.addEventListener("click", (event) => {
      if (!appShell.classList.contains("sidebar-open") || sidebar.contains(event.target) || sidebarPeek.contains(event.target)) return;
      if (mobileSidebar()) setSidebarOpen(false);
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && appShell.classList.contains("sidebar-open")) {
        setSidebarOpen(false);
        sidebarPeek.focus();
      }
    });
    sidebar.querySelectorAll("a,button").forEach((item) => item.addEventListener("click", () => {
      if (mobileSidebar() && item.id !== "create-cloud-sidebar") setSidebarOpen(false);
    }));
    window.addEventListener("resize", () => {
      if (!mobileSidebar()) appShell.classList.remove("sidebar-mobile-open");
      else if (appShell.classList.contains("sidebar-open")) setSidebarOpen(false);
    });
    if (mobileSidebar()) setSidebarOpen(false);
  }

  const modalAnimations = new WeakMap();
  function motionAllowed() {
    return !!window.gsap && !document.documentElement.classList.contains("reduce-motion") && !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  }
  function openModal(overlay) {
    if (!overlay) return;
    const running = modalAnimations.get(overlay);
    if (running) running.kill();
    if (!overlay.classList.contains("active")) modalReturnFocus.set(overlay, document.activeElement);
    overlay.classList.add("active");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    const panel = overlay.querySelector(".modal");
    if (motionAllowed() && panel) {
      const timeline = window.gsap.timeline();
      modalAnimations.set(overlay, timeline);
      timeline.fromTo(overlay, { autoAlpha: 0 }, { autoAlpha: 1, duration: .18, clearProps: "opacity,visibility" }, 0)
        .fromTo(panel, { autoAlpha: 0, y: 8, scale: .99 }, { autoAlpha: 1, y: 0, scale: 1, duration: .22, ease: "power2.out", clearProps: "opacity,visibility,transform" }, 0);
    }
    const target = overlay.querySelector('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (target) window.setTimeout(() => target.focus({ preventScroll: true }), 30);
  }

  function closeModal(overlay, onClosed) {
    if (!overlay) return;
    const finalize = () => {
      overlay.classList.remove("active");
      overlay.setAttribute("aria-hidden", "true");
      if (!document.querySelector(".modal-overlay.active")) document.body.style.overflow = "";
      const previous = modalReturnFocus.get(overlay);
      modalReturnFocus.delete(overlay);
      if (previous && previous.isConnected && typeof previous.focus === "function") previous.focus({ preventScroll: true });
      if (onClosed) onClosed(previous);
    };
    const running = modalAnimations.get(overlay);
    if (running) running.kill();
    const panel = overlay.querySelector(".modal");
    if (overlay.classList.contains("active") && motionAllowed() && panel) {
      const timeline = window.gsap.timeline({ onComplete: finalize });
      modalAnimations.set(overlay, timeline);
      timeline.to(panel, { autoAlpha: 0, y: 5, duration: .14, ease: "power1.in" }, 0)
        .to(overlay, { autoAlpha: 0, duration: .16, ease: "power1.in" }, 0);
    } else finalize();
  }

  window.kumoOpenModal = openModal;
  window.kumoCloseModal = closeModal;

  document.addEventListener("click", function (e) {
    if (e.target.classList && e.target.classList.contains("modal-overlay")) {
      closeModal(e.target);
    }
  });

  document.addEventListener("keydown", function (e) {
    const activeOverlays = Array.from(document.querySelectorAll(".modal-overlay.active"));
    if (e.key === "Escape") {
      if (activeOverlays.length) {
        e.preventDefault();
        e.stopImmediatePropagation();
        activeOverlays.forEach(closeModal);
      }
      hidePopover();
      return;
    }
    if (e.key !== "Tab" || !activeOverlays.length) return;
    const overlay = activeOverlays[activeOverlays.length - 1];
    const focusable = Array.from(overlay.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
      .filter((element) => !element.hidden && element.getAttribute("aria-hidden") !== "true" && element.getClientRects().length > 0);
    if (!focusable.length) { e.preventDefault(); return; }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && (document.activeElement === first || !overlay.contains(document.activeElement))) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && (document.activeElement === last || !overlay.contains(document.activeElement))) {
      e.preventDefault(); first.focus();
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
    if (!progressEl) return;
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
      const finishButton = document.getElementById("ob-finish");
      if (finishButton) {
        finishButton.addEventListener("click", () => {
          const groupField = { university: "semester", highschool: "grade", middleschool: "grade", elementary: "grade", work: "role" }[ob.stage];
          const groupName = (ob.answers[groupField] || "").trim();
          if (!groupName || !ob.answers.list.some((item) => item.trim() !== "")) {
            const error = document.getElementById("onboarding-error");
            if (error) { error.hidden = false; error.textContent = "Please enter a term or grade and at least one item."; }
            return;
          }
          sendSetupData(ob.stage, groupName, ob.answers.list);
        });
      }
    }
  }

  async function finishOnboarding(markSkipped = true) {
    try { localStorage.setItem("kumoOnboardingComplete", "1"); } catch (e) {}
    if (markSkipped) {
      const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
      try {
        await fetch("../assets/backend/setup_sky.php", {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": token },
          body: JSON.stringify({ stage: "skip" }),
        });
      } catch (e) { /* Local dismissal still works if the server is temporarily unavailable. */ }
    }
    closeModal(onboardingOverlay);
  }

  const closeOnboardingButton = document.getElementById("close-onboarding-modal");
  if (closeOnboardingButton) closeOnboardingButton.addEventListener("click", () => finishOnboarding(true));

  function startOnboarding() {
    if (!onboardingOverlay || !viewport || !progressEl) return;
    resetOnboarding();
    openModal(onboardingOverlay);
    renderStep("forward");
  }
  // The PHP dashboard decides whether a first-time account needs setup.
  // Expose this function instead of opening a blank overlay on every page load.
  window.kumoStartOnboarding = startOnboarding;

  const arrangeBtn = document.querySelector(".arrange:not(#welcome-create-cloud)");

  const settingsOverlay = document.getElementById("settings-modal");
  const settingsBtn = document.querySelector(".settings");
  const profileMenuButton = document.getElementById("profile-menu-button");
  const settingsMessage = document.getElementById("settings-message");
  const cloudModal = document.getElementById("cloud-modal");

  function showSettingsMessage(message, type = "success") {
    if (!settingsMessage) return;
    settingsMessage.hidden = !message;
    settingsMessage.textContent = message || "";
    settingsMessage.dataset.state = type;
  }

  function activateSettingsTab(tabName) {
    document.querySelectorAll(".settings-tab").forEach((tab) => {
      const active = tab.dataset.tab === tabName;
      tab.classList.toggle("active", active);
      tab.setAttribute("aria-selected", String(active));
    });
    document.querySelectorAll(".settings-panel").forEach((panel) => {
      panel.classList.toggle("active", panel.dataset.panel === tabName);
    });
    showSettingsMessage("");
  }

  function openSettings(tabName = "appearance") {
    activateSettingsTab(tabName);
    openModal(settingsOverlay);
  }
  if (settingsBtn) settingsBtn.addEventListener("click", () => openSettings("appearance"));
  if (profileMenuButton) profileMenuButton.addEventListener("click", () => openSettings("profile"));
  const closeSettingsButton = document.getElementById("close-settings-modal");
  if (closeSettingsButton) closeSettingsButton.addEventListener("click", () => closeModal(settingsOverlay));
  document.querySelectorAll(".settings-tab").forEach((tab) => tab.addEventListener("click", () => activateSettingsTab(tab.dataset.tab)));

  function getCookieSafePreferences() {
    try { return JSON.parse(localStorage.getItem("kumoPreferences") || "{}"); } catch (_) { return {}; }
  }
  function applyVisualPreferences(preferences) {
    const root = document.documentElement;
    const theme = ["light", "dark", "system"].includes(preferences.theme) ? preferences.theme : "light";
    const isSystemDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    root.dataset.themePreference = theme;
    root.dataset.theme = theme === "system" ? (isSystemDark ? "dark" : "light") : theme;
    root.dataset.accent = ["sage", "forest", "ocean", "lavender"].includes(preferences.accent_color) ? preferences.accent_color : "sage";
    root.dataset.density = ["comfortable", "compact"].includes(preferences.density) ? preferences.density : "comfortable";
    root.classList.toggle("reduce-motion", preferences.reduce_motion === true || preferences.reduce_motion === 1 || preferences.reduce_motion === "1");
    updateThemeToggleLabel();
  }

  function updateThemeToggleLabel() {
    const toggle = document.getElementById("theme-toggle");
    if (!toggle) return;
    const isDark = document.documentElement.dataset.theme === "dark";
    const target = isDark ? "light" : "dark";
    const icon = toggle.querySelector(".material-symbols-outlined");
    if (icon) icon.textContent = isDark ? "light_mode" : "dark_mode";
    toggle.setAttribute("aria-label", `Switch to ${target} theme`);
    toggle.title = `Switch to ${target} theme`;
    toggle.setAttribute("aria-pressed", String(isDark));
  }

  function readPreferenceControls() {
    const value = (id, fallback) => document.getElementById(id)?.value ?? fallback;
    const flag = (name) => document.querySelector(`#settings-modal input[name="${name}"]`)?.checked ? "1" : "0";
    return {
      theme: value("theme-choice", document.querySelector('meta[name="kumo-theme"]')?.content || "light"),
      accent_color: value("accent-choice", document.querySelector('meta[name="kumo-accent"]')?.content || "sage"),
      density: value("density-choice", "comfortable"),
      first_section: value("first-section-choice", "clouds"),
      reduce_motion: flag("reduce_motion"),
      show_clouds: flag("show_clouds"),
      show_upcoming: flag("show_upcoming"),
      show_recent_activity: flag("show_recent_activity"),
      show_progress: flag("show_progress"),
    };
  }

  function applyDashboardPreferences(preferences) {
    const main = document.querySelector(".main");
    const overview = document.querySelector(".dashboard-overview");
    const isRootDashboard = (document.querySelector('meta[name="current-cloud-id"]')?.content || "0") === "0";
    const sectionVisibility = {
      clouds: preferences.show_clouds,
      upcoming: preferences.show_upcoming,
      recent: preferences.show_recent_activity,
      progress: preferences.show_progress,
    };
    Object.entries(sectionVisibility).forEach(([name, enabled]) => {
      const section = document.querySelector(`[data-dashboard-section="${name}"]`);
      if (section && isRootDashboard) section.hidden = String(enabled) === "0";
    });
    if (overview && isRootDashboard) {
      const overviewCards = Array.from(overview.querySelectorAll('.overview-card'));
      overview.hidden = overviewCards.length > 0 && overviewCards.every((card) => card.hidden);
    }
    const cloudsSection = document.querySelector('[data-dashboard-section="clouds"]');
    if (!main || !overview || !isRootDashboard) return;
    const selected = ["clouds", "upcoming", "recent"].includes(preferences.first_section) ? preferences.first_section : "clouds";
    const target = main.querySelector(`[data-dashboard-section="${selected}"]`);
    if (selected === "clouds") {
      if (cloudsSection && overview.parentElement === main) main.insertBefore(cloudsSection, overview);
    } else {
      if (target && target.parentElement === overview) overview.insertBefore(target, overview.firstElementChild);
      if (cloudsSection && overview.parentElement === main) main.insertBefore(overview, cloudsSection);
    }
  }

  async function requestJson(url, data, isFormData = false) {
    const options = { method: "POST", headers: { "Accept": "application/json" }, body: isFormData ? data : new URLSearchParams(data) };
    if (!isFormData) options.headers["Content-Type"] = "application/x-www-form-urlencoded;charset=UTF-8";
    const response = await fetch(url, options);
    const result = await response.json().catch(() => ({ success: false, error: "The server returned an unreadable response." }));
    if (!response.ok || !result.success) throw new Error(result.error || "The request could not be completed.");
    return result;
  }

  async function saveAllPreferences() {
    const preferences = readPreferenceControls();
    preferences.csrf_token = document.querySelector('meta[name="csrf-token"]')?.content || "";
    try {
      const result = await requestJson("../assets/backend/save_preferences.php", preferences);
      const saved = result.preferences || preferences;
      saved.density = preferences.density;
      saved.first_section = preferences.first_section;
      saved.theme = preferences.theme;
      saved.accent_color = preferences.accent_color;
      saved.reduce_motion = preferences.reduce_motion;
      try {
        localStorage.setItem("kumoPreferences", JSON.stringify(saved));
        localStorage.setItem("kumoTheme", preferences.theme);
      } catch (_) {}
      applyVisualPreferences(saved);
      applyDashboardPreferences(saved);
      showSettingsMessage(result.message || "Settings saved.", "success");
      return true;
    } catch (error) {
      showSettingsMessage(error.message || "Settings could not be saved.", "error");
      return false;
    }
  }

  const themeToggle = document.getElementById("theme-toggle");
  if (themeToggle) themeToggle.addEventListener("click", async () => {
    const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    const choice = document.getElementById("theme-choice");
    if (choice) choice.value = nextTheme;
    applyVisualPreferences({ ...readPreferenceControls(), theme: nextTheme });
    const saved = await saveAllPreferences();
    if (!saved) {
      openSettings("appearance");
      showSettingsMessage("Theme could not be saved. Check the connection and try again.", "error");
    }
  });

  ["appearance-settings-form", "dashboard-settings-form"].forEach((id) => {
    const form = document.getElementById(id);
    if (form) form.addEventListener("submit", (event) => { event.preventDefault(); saveAllPreferences(); });
  });
  ["theme-choice", "accent-choice", "density-choice"].forEach((id) => {
    const control = document.getElementById(id);
    if (control) control.addEventListener("change", () => applyVisualPreferences(readPreferenceControls()));
  });
  const reduceMotionControl = document.querySelector('#settings-modal input[name="reduce_motion"]');
  if (reduceMotionControl) reduceMotionControl.addEventListener("change", () => applyVisualPreferences(readPreferenceControls()));
  const resetDashboard = document.getElementById("reset-dashboard-settings");
  if (resetDashboard) resetDashboard.addEventListener("click", () => {
    ["show_clouds", "show_upcoming", "show_recent_activity", "show_progress"].forEach((name) => {
      const control = document.querySelector(`#settings-modal input[name="${name}"]`);
      if (control) control.checked = true;
    });
    const firstSection = document.getElementById("first-section-choice");
    if (firstSection) firstSection.value = "clouds";
    saveAllPreferences();
  });

  const profileForm = document.getElementById("profile-settings-form");
  if (profileForm) profileForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = profileForm.querySelector('button[type="submit"]');
    if (submit) { submit.disabled = true; submit.textContent = "Saving…"; }
    try {
      const result = await requestJson(profileForm.action, Object.fromEntries(new FormData(profileForm).entries()));
      const displayName = result.display_name || profileForm.elements.full_name.value.trim();
      const userName = document.querySelector(".user-display-name");
      const welcome = document.querySelector(".welcome h1");
      const profileHead = document.querySelector(".profile-edit-head > div > strong");
      if (userName) userName.textContent = displayName;
      if (welcome) welcome.textContent = `Welcome back, ${displayName}.`;
      if (profileHead) profileHead.textContent = displayName;
      showSettingsMessage(result.message || "Profile saved.", "success");
    } catch (error) { showSettingsMessage(error.message || "Profile could not be saved.", "error"); }
    finally { if (submit) { submit.disabled = false; submit.textContent = "Save profile"; } }
  });

  const passwordForm = document.getElementById("change-password-form");
  if (passwordForm) passwordForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = passwordForm.querySelector('button[type="submit"]');
    if (submit) { submit.disabled = true; submit.textContent = "Updating…"; }
    try {
      const result = await requestJson(passwordForm.action, Object.fromEntries(new FormData(passwordForm).entries()));
      passwordForm.reset();
      if (typeof result.csrf_token === "string" && result.csrf_token.length >= 32) {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) csrfMeta.content = result.csrf_token;
        document.querySelectorAll('input[name="csrf_token"]').forEach((field) => { field.value = result.csrf_token; });
      }
      showSettingsMessage(result.message || "Password changed.", "success");
    } catch (error) { showSettingsMessage(error.message || "Password could not be changed.", "error"); }
    finally { if (submit) { submit.disabled = false; submit.textContent = "Change password"; } }
  });

  const avatarForm = document.getElementById("avatar-upload-form");
  const avatarFile = document.getElementById("avatar-file");
  let avatarPreviewUrl = null;
  if (avatarFile) avatarFile.addEventListener("change", () => {
    const file = avatarFile.files?.[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 2 * 1024 * 1024) {
      showSettingsMessage("Choose a JPEG, PNG, or WebP image no larger than 2 MB.", "error");
      avatarFile.value = "";
      return;
    }
    const fallback = document.getElementById("avatar-preview-fallback");
    let preview = document.getElementById("avatar-preview");
    if (avatarPreviewUrl) URL.revokeObjectURL(avatarPreviewUrl);
    avatarPreviewUrl = URL.createObjectURL(file);
    if (!preview || preview.tagName !== "IMG") {
      preview = document.createElement("img");
      preview.className = "profile-avatar-preview";
      preview.id = "avatar-preview";
      preview.alt = "Selected profile picture preview";
      if (fallback) fallback.replaceWith(preview);
      else avatarFile.closest(".profile-edit-head")?.prepend(preview);
    }
    preview.src = avatarPreviewUrl;
    preview.alt = "Selected profile picture preview";
    showSettingsMessage("Preview ready. Select Upload picture to save it.", "success");
  });
  if (avatarForm) avatarForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!avatarFile?.files?.length) { showSettingsMessage("Choose an image first.", "error"); return; }
    const submit = document.getElementById("avatar-upload-button");
    if (submit) { submit.disabled = true; submit.textContent = "Uploading…"; }
    try {
      const result = await requestJson(avatarForm.action, new FormData(avatarForm), true);
      showSettingsMessage(result.message || "Profile picture updated.", "success");
      window.location.reload();
    } catch (error) { showSettingsMessage(error.message || "Profile picture could not be uploaded.", "error"); }
    finally { if (submit) { submit.disabled = false; submit.textContent = "Upload picture"; } }
  });
  const removeAvatarButton = document.getElementById("remove-avatar-button");
  if (removeAvatarButton) removeAvatarButton.addEventListener("click", async () => {
    removeAvatarButton.disabled = true;
    const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
    try {
      const result = await requestJson("../assets/backend/upload_avatar.php", { csrf_token: token, action: "remove" });
      showSettingsMessage(result.message || "Profile picture removed.", "success");
      window.location.reload();
    } catch (error) { showSettingsMessage(error.message || "Profile picture could not be removed.", "error"); removeAvatarButton.disabled = false; }
  });

  const localDate = document.getElementById("local-date");
  if (localDate) {
    const now = new Date();
    localDate.textContent = new Intl.DateTimeFormat(undefined, { weekday: "short", month: "short", day: "numeric" }).format(now);
    localDate.dateTime = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
  }

  const cloudModalClose = document.getElementById("close-cloud-modal");
  function openCloudModal() {
    openModal(cloudModal);
    const nameInput = document.getElementById("cloud-name");
    if (nameInput) window.setTimeout(() => nameInput.focus({ preventScroll: true }), 60);
  }
  window.kumoOpenCloudModal = openCloudModal;
  ["create-cloud-sidebar", "empty-create-cloud", "welcome-create-cloud", "btn-open-category"].forEach((id) => {
    const trigger = document.getElementById(id);
    if (trigger) trigger.addEventListener("click", openCloudModal);
  });
  document.querySelectorAll("[data-open-cloud]").forEach((trigger) => trigger.addEventListener("click", openCloudModal));
  if (cloudModalClose) cloudModalClose.addEventListener("click", () => closeModal(cloudModal));

  const createCloudForm = document.getElementById("create-cloud-form");
  const createCloudButton = document.getElementById("create-cloud-confirm");
  if (createCloudForm && createCloudButton) createCloudForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const nameInput = document.getElementById("cloud-name");
    const locationInput = document.getElementById("cloud-location");
    const message = document.getElementById("cloud-create-message");
    const name = nameInput ? nameInput.value.trim() : "";
    const location = locationInput ? locationInput.value : "root";
    if (!name) {
      if (message) { message.hidden = false; message.textContent = "Give your Cloud a name first."; }
      if (nameInput) nameInput.focus();
      return;
    }
    if (name.length > 100 || /[\u0000-\u001f\u007f]/.test(name)) {
      if (message) { message.hidden = false; message.textContent = "Use a Cloud name up to 100 characters without control characters."; }
      if (nameInput) nameInput.focus();
      return;
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const currentCloudId = document.querySelector('meta[name="current-cloud-id"]')?.content || "0";
    createCloudButton.disabled = true;
    createCloudButton.textContent = "Creating…";
    createCloudForm.setAttribute("aria-busy", "true");
    if (message) { message.hidden = true; message.textContent = ""; }
    try {
      const response = await fetch("../assets/backend/create_cloud.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8", "Accept": "application/json" },
        body: new URLSearchParams({ name, location, current_cloud_id: currentCloudId, csrf_token: token }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "Could not create this Cloud.");
      if (message) { message.hidden = false; message.textContent = "Cloud created. Opening it…"; }
      window.location.assign(result.redirect || "homepage.php");
    } catch (error) {
      if (message) { message.hidden = false; message.textContent = error.message || "Something went wrong. Please try again."; }
    } finally {
      createCloudButton.disabled = false;
      createCloudButton.textContent = "Create Cloud";
      createCloudForm.removeAttribute("aria-busy");
    }
  });

  const atmosphereModal = document.getElementById("atmosphere-modal");
  const atmosphereTrigger = document.getElementById("join-atmosphere-trigger");
  const closeAtmosphere = document.getElementById("close-atmosphere-modal");
  const dismissAtmosphere = document.getElementById("dismiss-atmosphere-modal");
  if (atmosphereTrigger) atmosphereTrigger.addEventListener("click", () => openModal(atmosphereModal));
  if (closeAtmosphere) closeAtmosphere.addEventListener("click", () => closeModal(atmosphereModal));
  if (dismissAtmosphere) dismissAtmosphere.addEventListener("click", () => closeModal(atmosphereModal));

  // The authenticated account's server-rendered settings are the source of truth.
  // Local storage is only a convenience for unauthenticated/auth pages and must not
  // override a newer preference saved on another device.
  const serverPreferences = readPreferenceControls();
  serverPreferences.theme = document.querySelector('meta[name="kumo-theme"]')?.content || serverPreferences.theme;
  serverPreferences.accent_color = document.querySelector('meta[name="kumo-accent"]')?.content || serverPreferences.accent_color;
  serverPreferences.density = document.querySelector('meta[name="kumo-density"]')?.content || serverPreferences.density;
  serverPreferences.reduce_motion = document.querySelector('meta[name="kumo-reduce-motion"]')?.content || serverPreferences.reduce_motion;
  applyVisualPreferences(serverPreferences);
  applyDashboardPreferences(serverPreferences);
  try {
    localStorage.setItem("kumoPreferences", JSON.stringify(serverPreferences));
    localStorage.setItem("kumoTheme", serverPreferences.theme);
  } catch (_) {}
  if (window.matchMedia) {
    const scheme = window.matchMedia("(prefers-color-scheme: dark)");
    const syncSystemTheme = () => {
      const selectedTheme = document.getElementById("theme-choice")?.value || document.documentElement.dataset.themePreference || serverPreferences.theme;
      if (selectedTheme === "system") applyVisualPreferences({ ...readPreferenceControls(), theme: "system" });
    };
    if (typeof scheme.addEventListener === "function") scheme.addEventListener("change", syncSystemTheme);
    else if (typeof scheme.addListener === "function") scheme.addListener(syncSystemTheme);
  }

  const searchInput = document.getElementById("search-input");
  const sidebarSearch = document.getElementById("sidebar-search");
  let searchFeedback = null;
  function filterVisibleClouds() {
    if (!searchInput) return;
    const query = searchInput.value.trim().toLowerCase();
    const items = Array.from(document.querySelectorAll("#cloud-tree .nav-link, .cloud-preview-grid .cloud-card-link"));
    let matchedCount = 0;
    items.forEach((item) => {
      const matches = !query || item.textContent.toLowerCase().includes(query);
      item.hidden = !matches;
      if (matches) {
        matchedCount += 1;
        let ancestor = item.parentElement?.closest("details.cloud-folder");
        while (ancestor) {
          ancestor.open = true;
          ancestor.hidden = false;
          const ancestorSummary = ancestor.querySelector(":scope > summary.nav-link");
          if (ancestorSummary) ancestorSummary.hidden = false;
          ancestor = ancestor.parentElement?.closest("details.cloud-folder");
        }
      }
    });
    if (!searchFeedback) {
      searchFeedback = document.createElement("p");
      searchFeedback.id = "cloud-search-feedback";
      searchFeedback.className = "search-feedback";
      searchFeedback.setAttribute("role", "status");
      searchFeedback.setAttribute("aria-live", "polite");
      const searchBar = document.querySelector(".search");
      const cloudGrid = document.querySelector(".cloud-preview-grid");
      const cloudTree = document.getElementById("cloud-tree");
      (searchBar || cloudGrid || cloudTree || searchInput.parentElement)?.after(searchFeedback);
    }
    searchFeedback.hidden = !query || matchedCount > 0;
    searchFeedback.textContent = query && matchedCount === 0 ? `No Clouds match “${searchInput.value.trim()}”. Try another search or create a Cloud.` : "";
  }
  if (searchInput) searchInput.addEventListener("input", filterVisibleClouds);
  if (sidebarSearch && searchInput) {
    sidebarSearch.addEventListener("click", (event) => {
      event.preventDefault();
      searchInput.focus();
      searchInput.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
  }
  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      if (searchInput) searchInput.focus();
    }
  });

  const popoverEl = document.getElementById("info-popover-template");
  const popoverTitle = document.getElementById("info-popover-title");
  const popoverBody = document.getElementById("info-popover-body");
  let popoverOwner = null;

  function showPopover(trigger, title, body) {
    if (window.gsap) window.gsap.killTweensOf(popoverEl);
    if (popoverOwner === trigger && popoverEl.classList.contains("visible")) {
      hidePopover();
      return;
    }
    popoverTitle.textContent = title;
    popoverBody.textContent = body;
    popoverEl.classList.add("visible");
    popoverOwner = trigger;
    if (motionAllowed()) window.gsap.fromTo(popoverEl, { autoAlpha: 0, y: 5 }, { autoAlpha: 1, y: 0, duration: .16, ease: "power1.out", clearProps: "opacity,visibility,transform" });

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
    if (popoverOwner && popoverEl.classList.contains("visible") && motionAllowed()) {
      const owner = popoverOwner;
      window.gsap.to(popoverEl, { autoAlpha: 0, y: 3, duration: .12, onComplete: () => {
        if (popoverOwner === owner) { popoverEl.classList.remove("visible"); popoverEl.style.cssText = ""; popoverOwner = null; }
      } });
      return;
    }
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

  if (arrangeBtn) arrangeBtn.addEventListener("click", () => openSettings("dashboard"));

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
    this.steps = [
      { title: "Welcome to Kumo", text: "A calm workspace for your classes, projects, notes, and next steps.", target: ".welcome" },
      { title: "Your navigation", text: "Keep your Clouds and workspace tools close. Collapse this rail when you want more room to focus.", target: "#sidebar-peek" },
      { title: "Create a Cloud", text: "Clouds give each class, project, or part of life its own organized space.", target: "#create-cloud-sidebar", sidebar: true },
      { title: "Find anything", text: "Search your saved Clouds to quickly return to a space.", target: "#search-input" },
      { title: "Add your first Cloud", text: "You can also start a new Cloud right here on your dashboard.", target: "#welcome-create-cloud" },
      { title: "Arrange your Sky", text: "Use Arrange Your Sky to organize the spaces on your dashboard.", target: "#arrange-your-sky" },
      { title: "Your Clouds", text: "Open a Cloud to find its notes, tasks, and other saved content.", target: ".clouds-section" },
      { title: "Small steps", text: "Keep upcoming tasks in view and make progress one step at a time.", target: "#task-overview-panel" },
      { title: "Choose your theme", text: "Switch between light and dark whenever you like.", target: "#theme-toggle" },
      { title: "Make Kumo yours", text: "Settings lets you tune appearance, dashboard sections, and more. Reopen this tour any time from Help & tour.", target: ".settings", sidebar: true },
    ];
    this.currentStep = 0;
    this.highlighted = null;
    this.timeline = null;
    this.init();
  }
  init() {
    this.overlay = document.createElement("div");
    this.overlay.className = "tour-overlay";
    this.overlay.setAttribute("aria-hidden", "true");
    this.tooltip = document.createElement("section");
    this.tooltip.className = "tour-tooltip";
    this.tooltip.setAttribute("role", "dialog");
    this.tooltip.setAttribute("aria-modal", "true");
    this.tooltip.setAttribute("aria-labelledby", "tour-title");
    this.tooltip.setAttribute("aria-describedby", "tour-text");
    this.tooltip.setAttribute("tabindex", "-1");
    this.tooltip.innerHTML = '<button type="button" class="tour-close" aria-label="Close tour">×</button><div class="tour-kicker"><span id="tour-count"></span><span>KUMO TOUR</span></div><div class="tour-progress" role="progressbar" aria-label="Tour progress" aria-valuemin="1" aria-valuemax="10" aria-valuenow="1"><span></span></div><h3 id="tour-title"></h3><p id="tour-text"></p><div class="tour-actions"><button type="button" class="tour-skip">Skip</button><button type="button" class="tour-back">Back</button><button type="button" class="tour-next"></button></div>';
    document.body.append(this.overlay, this.tooltip);
    this.title = this.tooltip.querySelector("#tour-title");
    this.text = this.tooltip.querySelector("#tour-text");
    this.count = this.tooltip.querySelector("#tour-count");
    this.progress = this.tooltip.querySelector(".tour-progress");
    this.bar = this.progress.firstElementChild;
    this.back = this.tooltip.querySelector(".tour-back");
    this.next = this.tooltip.querySelector(".tour-next");
    this.tooltip.querySelector(".tour-close").addEventListener("click", () => this.endTour());
    this.tooltip.querySelector(".tour-skip").addEventListener("click", () => this.endTour());
    this.back.addEventListener("click", () => this.previousStep());
    this.next.addEventListener("click", () => this.nextStep());
    this.onViewport = () => this.position();
    document.addEventListener("keydown", (event) => {
      if (!this.active) return;
      if (event.key === "Escape") { event.preventDefault(); this.endTour(); }
      if (event.key === "Tab") {
        const controls = [...this.tooltip.querySelectorAll("button:not([hidden])")];
        if (event.shiftKey && document.activeElement === controls[0]) { event.preventDefault(); controls.at(-1).focus(); }
        else if (!event.shiftKey && document.activeElement === controls.at(-1)) { event.preventDefault(); controls[0].focus(); }
      }
    });
  }
  availableSteps() {
    return this.steps.filter((step) => {
      if (!step.target) return true;
      const target = document.querySelector(step.target);
      return !!target && !target.closest("[hidden]") && target.getClientRects().length > 0;
    });
  }
  start() {
    if (this.active) return;
    if (this.timeline) this.timeline.kill();
    if (window.gsap) {
      window.gsap.killTweensOf([this.tooltip, this.overlay]);
      window.gsap.set([this.tooltip, this.overlay], { clearProps: "opacity,visibility,transform" });
    }
    this.steps = this.availableSteps();
    if (!this.steps.length) return;
    this.active = true;
    this.currentStep = 0;
    this.returnFocus = window.kumoTourFocusReturn || document.activeElement;
    window.kumoTourFocusReturn = null;
    this.sidebar = document.getElementById("kumo-sidebar");
    this.sidebarPeek = document.getElementById("sidebar-peek");
    this.restoreSidebarClosed = !!this.sidebar && !this.sidebar.classList.contains("sidebar-open");
    this.overlay.classList.add("active");
    this.overlay.setAttribute("aria-hidden", "false");
    document.body.classList.add("kumo-tour-active");
    window.addEventListener("resize", this.onViewport);
    window.addEventListener("scroll", this.onViewport, true);
    if (window.gsap && this.motionAllowed()) window.gsap.fromTo(this.overlay, { autoAlpha: 0 }, { autoAlpha: 1, duration: .2, clearProps: "opacity,visibility" });
    this.showStep(true);
  }
  motionAllowed() { return !document.documentElement.classList.contains("reduce-motion") && !window.matchMedia("(prefers-reduced-motion: reduce)").matches; }
  showStep(first = false) {
    const step = this.steps[this.currentStep];
    if (!step) return this.endTour(false);
    if (this.timeline) this.timeline.kill();
    if (window.gsap) window.gsap.set(this.tooltip, { clearProps: "opacity,visibility,transform" });
    const previousWasSidebar = !!this.highlighted?.closest("#kumo-sidebar");
    if (this.highlighted) this.highlighted.classList.remove("tour-highlight");
    if (this.sidebar) this.sidebar.classList.remove("kumo-tour-target-layer");
    if (step.sidebar && this.sidebar && !this.sidebar.classList.contains("sidebar-open") && this.sidebarPeek) this.sidebarPeek.click();
    else if (!step.sidebar && previousWasSidebar && this.restoreSidebarClosed && this.sidebar?.classList.contains("sidebar-open")) this.sidebarPeek?.click();
    const target = step.target ? document.querySelector(step.target) : null;
    if (step.target && (!target || !target.getClientRects().length)) {
      this.steps.splice(this.currentStep, 1);
      if (!this.steps.length) return this.endTour(false);
      this.currentStep = Math.min(this.currentStep, this.steps.length - 1);
      return this.showStep(first);
    }
    this.highlighted = target;
    if (target) {
      target.classList.add("tour-highlight");
      if (target.closest("#kumo-sidebar")) this.sidebar.classList.add("kumo-tour-target-layer");
      target.scrollIntoView({ block: "center", inline: "nearest", behavior: this.motionAllowed() ? "smooth" : "auto" });
    }
    this.title.textContent = step.title;
    this.text.textContent = step.text;
    this.count.textContent = `Step ${this.currentStep + 1} of ${this.steps.length}`;
    this.progress.setAttribute("aria-valuemax", String(this.steps.length));
    this.progress.setAttribute("aria-valuenow", String(this.currentStep + 1));
    this.bar.style.width = `${((this.currentStep + 1) / this.steps.length) * 100}%`;
    this.back.hidden = this.currentStep === 0;
    this.next.textContent = this.currentStep === this.steps.length - 1 ? "Finish" : "Next";
    this.tooltip.classList.add("active");
    this.tooltip.style.visibility = "hidden";
    this.position();
    this.tooltip.style.visibility = "";
    if (window.gsap && this.motionAllowed()) {
      this.timeline = window.gsap.timeline();
      this.timeline.fromTo(this.tooltip, { autoAlpha: 0, y: 7 }, { autoAlpha: 1, y: 0, duration: .2, ease: "power2.out", clearProps: "opacity,visibility,transform" });
    }
    if (first) this.next.focus({ preventScroll: true });
  }
  position() {
    if (!this.tooltip || !this.active) return;
    const pad = 12;
    const rect = this.highlighted?.getBoundingClientRect();
    const tip = this.tooltip.getBoundingClientRect();
    let left = (innerWidth - tip.width) / 2, top = (innerHeight - tip.height) / 2;
    if (rect) {
      left = rect.right + 14;
      top = rect.top + (rect.height - tip.height) / 2;
      if (left + tip.width > innerWidth - pad) left = rect.left - tip.width - 14;
      if (left < pad) { left = Math.max(pad, Math.min(rect.left, innerWidth - tip.width - pad)); top = rect.bottom + 14; }
    }
    this.tooltip.style.left = `${Math.max(pad, Math.min(left, innerWidth - tip.width - pad))}px`;
    this.tooltip.style.top = `${Math.max(pad, Math.min(top, innerHeight - tip.height - pad))}px`;
    this.tooltip.style.transform = "none";
  }
  nextStep() { if (this.currentStep < this.steps.length - 1) { this.currentStep++; this.showStep(); } else this.endTour(true); }
  previousStep() { if (this.currentStep > 0) { this.currentStep--; this.showStep(); } }
  endTour(completed = false) {
    if (!this.active) return;
    this.active = false;
    if (completed) { try { localStorage.setItem("kumoTourComplete", "1"); } catch (_) {} }
    if (this.timeline) this.timeline.kill();
    if (window.gsap && this.motionAllowed()) this.timeline = window.gsap.to([this.tooltip, this.overlay], { autoAlpha: 0, duration: .16, onComplete: () => this.cleanup() });
    else this.cleanup();
  }
  cleanup() {
    this.overlay.classList.remove("active");
    this.overlay.setAttribute("aria-hidden", "true");
    this.tooltip.classList.remove("active");
    this.tooltip.style.cssText = "";
    if (this.highlighted) this.highlighted.classList.remove("tour-highlight");
    if (this.sidebar) this.sidebar.classList.remove("kumo-tour-target-layer");
    if (this.restoreSidebarClosed && this.sidebar?.classList.contains("sidebar-open")) this.sidebarPeek?.click();
    document.body.classList.remove("kumo-tour-active");
    window.removeEventListener("resize", this.onViewport);
    window.removeEventListener("scroll", this.onViewport, true);
    let focusTarget = this.returnFocus;
    if (focusTarget?.closest("#kumo-sidebar") && !this.sidebar?.classList.contains("sidebar-open")) focusTarget = this.sidebarPeek;
    if (focusTarget?.isConnected) focusTarget.focus({ preventScroll: true });
  }
}

async function finishSetup() {
  const semesterInput = document.getElementById("uni-semester")?.value.trim() || "";
  const classesRaw = document.getElementById("uni-classes")?.value.trim() || "";
  if (!semesterInput || !classesRaw) { alert("Please fill in your semester and classes."); return; }
  return sendSetupData("university", semesterInput, classesRaw);
}

async function finishSetupHS() {
  const gradeInput = document.getElementById("hs-grade")?.value.trim() || "";
  const classesRaw = document.getElementById("hs-classes")?.value.trim() || "";
  if (!gradeInput || !classesRaw) { alert("Please fill in your grade and subjects."); return; }
  return sendSetupData("highschool", gradeInput, classesRaw);
}

async function finishSetupMS() {
  const gradeInput = document.getElementById("ms-grade")?.value.trim() || "";
  const classesRaw = document.getElementById("ms-classes")?.value.trim() || "";
  if (!gradeInput || !classesRaw) { alert("Please fill in your grade and subjects."); return; }
  return sendSetupData("middleschool", gradeInput, classesRaw);
}

async function finishSetupElem() {
  const gradeInput = document.getElementById("elem-grade")?.value.trim() || "";
  const classesRaw = document.getElementById("elem-classes")?.value.trim() || "";
  if (!gradeInput || !classesRaw) { alert("Please fill in your grade and subjects."); return; }
  return sendSetupData("elementary", gradeInput, classesRaw);
}

async function finishSetupWork() {
  const titleInput = document.getElementById("work-title")?.value.trim() || "";
  const projectsRaw = document.getElementById("work-projects")?.value.trim() || "";
  if (!titleInput || !projectsRaw) { alert("Please fill in your title and responsibilities."); return; }
  return sendSetupData("work", titleInput, projectsRaw);
}

async function sendSetupData(stage, groupName, itemsRaw) {
  const items = Array.isArray(itemsRaw)
    ? itemsRaw.map((item) => String(item).trim()).filter(Boolean)
    : String(itemsRaw || "").split(",").map((item) => item.trim()).filter(Boolean);
  const fieldByStage = { university: "semester", highschool: "grade", middleschool: "grade", elementary: "grade", work: "role" };
  const field = fieldByStage[stage];
  const error = document.getElementById("onboarding-error");
  if (!field || !String(groupName || "").trim() || items.length === 0) {
    if (error) { error.hidden = false; error.textContent = "Please complete the requested fields before continuing."; }
    return;
  }
  const payload = { stage, [field]: String(groupName).trim(), items, classes: items };
  const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
  try {
    const response = await fetch("../assets/backend/setup_sky.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": token, "Accept": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || "Kumo could not save your setup.");
    try { localStorage.setItem("kumoOnboardingComplete", "1"); } catch (e) {}
    if (error) { error.hidden = true; error.textContent = ""; }
    if (typeof window.kumoStartOnboarding === "function") {
      // Close without sending a second (skip) request after the successful save.
      const overlay = document.getElementById("onboarding-modal");
      if (overlay) { overlay.classList.remove("active"); document.body.style.overflow = ""; }
    }
    window.location.reload();
  } catch (err) {
    if (error) { error.hidden = false; error.textContent = err.message || "Network error. Please try again."; }
    else alert(err.message || "Network error. Please try again.");
  }
}

if (document.getElementById("kumo-app")) {
  window.kumoTour = new KumoTour();
  const tourButton = document.getElementById("start-kumo-tour");
  if (tourButton) tourButton.addEventListener("click", () => {
    const settings = document.getElementById("settings-modal");
    window.kumoCloseModal(settings, (returnFocus) => {
      window.kumoTourFocusReturn = returnFocus;
      window.kumoTour.start();
    });
  });
}
