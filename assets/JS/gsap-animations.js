/**
 * Kumo — Premium Parallax Scrolling & Motion System
 * Powered by GSAP & ScrollTrigger
 *
 * Implements:
 * - Real continuous scroll-linked parallax (scrub: 1)
 * - Multi-layer visual depth (background atmospheric clouds, midground, foreground)
 * - Pinned storytelling feature deck with bidirectional scrub
 * - Responsive adaptations (Desktop, Tablet, Mobile)
 * - Strict reduced-motion & theme preference adherence
 */
(function () {
  "use strict";

  const gsap = window.gsap;
  const ScrollTrigger = window.ScrollTrigger;
  if (!gsap) return;

  if (ScrollTrigger) {
    gsap.registerPlugin(ScrollTrigger);
  }

  const mm = gsap.matchMedia();

  // Helper to query visible, non-hidden elements
  const visible = (selector, root = document) =>
    Array.from(root.querySelectorAll(selector)).filter(
      (el) => !el.closest("[hidden]") && el.getClientRects().length > 0
    );

  // Subtle accessible entrance helper
  const entrance = (elements, vars = {}) => {
    if (!elements || !elements.length) return;
    gsap.fromTo(
      elements,
      { autoAlpha: 0, y: 12 },
      {
        autoAlpha: 1,
        y: 0,
        duration: 0.48,
        ease: "power2.out",
        stagger: 0.055,
        clearProps: "opacity,visibility,transform",
        ...vars,
      }
    );
  };

  // ───────────────────────────────────────────────────────────────────────────
  // MatchMedia Definitions: Desktop (>=1001px), Tablet (621-1000px), Mobile (<=620px)
  // ───────────────────────────────────────────────────────────────────────────
  mm.add(
    {
      isDesktop: "(min-width: 1001px) and (prefers-reduced-motion: no-preference)",
      isTablet: "(min-width: 621px) and (max-width: 1000px) and (prefers-reduced-motion: no-preference)",
      isMobile: "(max-width: 620px) and (prefers-reduced-motion: no-preference)",
      reduceMotion: "(prefers-reduced-motion: reduce)",
    },
    (context) => {
      const { isDesktop, isTablet, isMobile, reduceMotion } = context.conditions;
      const userReduced = document.documentElement.classList.contains("reduce-motion");

      // ── ACCESSIBILITY: REDUCED MOTION ──────────────────────────────────────
      if (reduceMotion || userReduced) {
        // Render all elements in final static states; no continuous scrub or pinning
        const allStoryCards = document.querySelectorAll(".story-card");
        allStoryCards.forEach((c) => {
          c.style.position = "static";
          c.style.opacity = "1";
          c.style.transform = "none";
        });
        const landing = document.querySelector(".hero-section");
        if (landing) {
          entrance(visible(".hero-copy > *"), { duration: 0.3 });
          entrance(visible(".product-preview"), { duration: 0.3 });
        }
        const dashboard = document.querySelector(".main.dashboard-home");
        if (dashboard) {
          entrance(visible(".welcome, .dashboard-stats, .clouds-section", dashboard), { duration: 0.3 });
        }
        return;
      }

      // ── 1. LANDING PAGE SHOWCASE ───────────────────────────────────────────
      const landing = document.querySelector(".hero-section");
      if (landing && ScrollTrigger) {
        // A. Initial entrance animation
        const introTl = gsap.timeline({ defaults: { duration: 0.52, ease: "power2.out" } });
        introTl
          .fromTo(".site-nav", { autoAlpha: 0, y: -8 }, { autoAlpha: 1, y: 0, clearProps: "opacity,visibility,transform" })
          .fromTo(visible(".hero-copy > *"), { autoAlpha: 0, y: 12 }, { autoAlpha: 1, y: 0, stagger: 0.06, clearProps: "opacity,visibility,transform" }, "<0.1")
          .fromTo(".product-preview", { autoAlpha: 0, y: 16, scale: 0.98 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.65, clearProps: "opacity,visibility" }, "<0.08");

        if (isDesktop) {
          introTl.fromTo(
            ".hero-float-pill",
            { autoAlpha: 0, scale: 0.9, y: 10 },
            { autoAlpha: 1, scale: 1, y: 0, duration: 0.45, stagger: 0.08, clearProps: "opacity,visibility" },
            "<0.15"
          );
        }

        // B. Continuous Background Atmospheric Parallax (Whole Page Journey)
        const bgCloud1 = document.querySelector(".cloud-layer-bg-1");
        const bgCloud2 = document.querySelector(".cloud-layer-bg-2");
        const bgCloud3 = document.querySelector(".cloud-layer-bg-3");
        const heroGlow = document.querySelector(".hero-glow-orb");

        if (bgCloud1) {
          gsap.to(bgCloud1, {
            yPercent: isDesktop ? -30 : -15,
            xPercent: 8,
            ease: "none",
            scrollTrigger: {
              trigger: document.body,
              start: "top top",
              end: "bottom bottom",
              scrub: 1.2,
            },
          });
        }

        if (bgCloud2) {
          gsap.to(bgCloud2, {
            yPercent: isDesktop ? -50 : -25,
            xPercent: -10,
            ease: "none",
            scrollTrigger: {
              trigger: document.body,
              start: "top top",
              end: "bottom bottom",
              scrub: 0.9,
            },
          });
        }

        if (bgCloud3) {
          gsap.to(bgCloud3, {
            yPercent: isDesktop ? -65 : -30,
            xPercent: 12,
            ease: "none",
            scrollTrigger: {
              trigger: document.body,
              start: "top top",
              end: "bottom bottom",
              scrub: 1.4,
            },
          });
        }

        if (heroGlow) {
          gsap.to(heroGlow, {
            yPercent: -25,
            scale: 0.85,
            opacity: 0.4,
            ease: "none",
            scrollTrigger: {
              trigger: landing,
              start: "top top",
              end: "bottom top",
              scrub: 1,
            },
          });
        }

        // C. Hero Foreground & Layered Parallax
        const heroCopy = document.querySelector(".hero-copy");
        const previewEl = document.querySelector(".product-preview");

        if (heroCopy) {
          gsap.to(heroCopy, {
            y: isDesktop ? -45 : -20,
            opacity: 0.88,
            ease: "none",
            scrollTrigger: {
              trigger: landing,
              start: "top top",
              end: "bottom top",
              scrub: 0.7,
            },
          });
        }

        if (previewEl) {
          // Perspective tilt and vertical lag
          gsap.to(previewEl, {
            y: isDesktop ? -70 : -30,
            rotateX: isDesktop ? -4 : 0,
            rotateY: isDesktop ? 2 : 0,
            ease: "none",
            scrollTrigger: {
              trigger: landing,
              start: "top top",
              end: "bottom top",
              scrub: 1,
            },
          });

          // Floating badges with independent differential speeds
          if (isDesktop) {
            const pillClouds = previewEl.querySelector(".pill-clouds");
            const pillNotes = previewEl.querySelector(".pill-notes");
            const pillZen = previewEl.querySelector(".pill-zen");

            if (pillClouds) {
              gsap.to(pillClouds, {
                y: -95,
                x: -12,
                rotate: -3,
                ease: "none",
                scrollTrigger: {
                  trigger: landing,
                  start: "top top",
                  end: "bottom top",
                  scrub: 0.8,
                },
              });
            }

            if (pillNotes) {
              gsap.to(pillNotes, {
                y: -130,
                x: 14,
                rotate: 2.5,
                ease: "none",
                scrollTrigger: {
                  trigger: landing,
                  start: "top top",
                  end: "bottom top",
                  scrub: 1.25,
                },
              });
            }

            if (pillZen) {
              gsap.to(pillZen, {
                y: -60,
                x: 8,
                rotate: -1.5,
                ease: "none",
                scrollTrigger: {
                  trigger: landing,
                  start: "top top",
                  end: "bottom top",
                  scrub: 0.65,
                },
              });
            }

            // Preview window internal depth layers
            const previewStats = previewEl.querySelector(".preview-stats");
            const previewLower = previewEl.querySelector(".preview-lower");
            if (previewStats) {
              gsap.to(previewStats, {
                y: -18,
                ease: "none",
                scrollTrigger: {
                  trigger: landing,
                  start: "top top",
                  end: "bottom top",
                  scrub: 0.8,
                },
              });
            }
            if (previewLower) {
              gsap.to(previewLower, {
                y: -28,
                ease: "none",
                scrollTrigger: {
                  trigger: landing,
                  start: "top top",
                  end: "bottom top",
                  scrub: 1.1,
                },
              });
            }
          }
        }

        // D. Intro Strip Subtle Kinetic Scrub
        const introStrip = document.querySelector(".intro-strip");
        if (introStrip) {
          const stripLabel = introStrip.querySelector(".strip-label");
          const stripP = introStrip.querySelector("p");

          if (stripLabel && isDesktop) {
            gsap.to(stripLabel, {
              x: 28,
              ease: "none",
              scrollTrigger: {
                trigger: introStrip,
                start: "top bottom",
                end: "bottom top",
                scrub: 0.8,
              },
            });
          }

          if (stripP && isDesktop) {
            gsap.to(stripP, {
              x: -22,
              ease: "none",
              scrollTrigger: {
                trigger: introStrip,
                start: "top bottom",
                end: "bottom top",
                scrub: 0.8,
              },
            });
          }
        }

        // E. Pinned Interactive Feature Storytelling Deck (#how-it-works-showcase)
        const featureSection = document.querySelector("#how-it-works-showcase");
        const featureStage = document.querySelector(".feature-stage");
        const cards = visible(".story-card", featureSection);
        const dots = visible(".story-dot", featureSection);
        const stepText = featureSection ? featureSection.querySelector(".story-step-text") : null;

        if (featureSection && featureStage && cards.length === 3 && isDesktop) {
          // Initialize card layers
          gsap.set(cards[0], { y: 0, scale: 1, autoAlpha: 1, zIndex: 3 });
          gsap.set(cards[1], { y: 130, scale: 0.94, autoAlpha: 0, zIndex: 4 });
          gsap.set(cards[2], { y: 180, scale: 0.9, autoAlpha: 0, zIndex: 5 });

          const updateProgress = (activeIdx) => {
            dots.forEach((dot, idx) => dot.classList.toggle("active", idx === activeIdx));
            if (stepText) {
              stepText.textContent = `Depth Layer 0${activeIdx + 1} / 03`;
            }
          };

          // Continuous scrubbed pinned storytelling sequence
          const storyTimeline = gsap.timeline({
            scrollTrigger: {
              trigger: featureSection,
              start: "top top+=75",
              end: "+=160%",
              pin: true,
              scrub: 1,
              anticipatePin: 1,
              onUpdate: (self) => {
                const p = self.progress;
                if (p < 0.35) updateProgress(0);
                else if (p < 0.72) updateProgress(1);
                else updateProgress(2);
              },
            },
          });

          // Transition 0 -> 1: Card 1 steps back, Card 2 glides in
          storyTimeline
            .to(cards[0], { y: -24, scale: 0.96, autoAlpha: 0.32, ease: "power1.inOut" }, 0)
            .to(cards[1], { y: 0, scale: 1, autoAlpha: 1, ease: "power1.out" }, 0.08)

          // Transition 1 -> 2: Card 2 steps back, Card 3 docks into position
            .to(cards[0], { y: -44, scale: 0.92, autoAlpha: 0.12, ease: "power1.inOut" }, 0.5)
            .to(cards[1], { y: -24, scale: 0.96, autoAlpha: 0.32, ease: "power1.inOut" }, 0.5)
            .to(cards[2], { y: 0, scale: 1, autoAlpha: 1, ease: "power1.out" }, 0.58);
        } else if (cards.length) {
          // Responsive fallback: render cards cleanly
          cards.forEach((c) => {
            gsap.set(c, { clearProps: "all" });
          });
        }

        // Feature list items reveal
        const rows = visible(".feature-row", featureSection);
        if (rows.length) {
          rows.forEach((row, i) => {
            gsap.fromTo(
              row,
              { autoAlpha: 0, y: 16 },
              {
                autoAlpha: 1,
                y: 0,
                duration: 0.45,
                ease: "power2.out",
                scrollTrigger: {
                  trigger: row,
                  start: "top 90%",
                  toggleActions: "play none none reverse",
                },
              }
            );
          });
        }

        // F. Closing Section Depth Lift & Ambient Expansion
        const closingSection = document.querySelector(".closing-section");
        const closingGlow = document.querySelector(".closing-ambient-glow");

        if (closingSection) {
          gsap.to(closingSection, {
            y: isDesktop ? -32 : -16,
            scale: isDesktop ? 1.012 : 1,
            ease: "none",
            scrollTrigger: {
              trigger: closingSection,
              start: "top bottom",
              end: "bottom bottom",
              scrub: 1,
            },
          });

          if (closingGlow) {
            gsap.to(closingGlow, {
              scale: 1.3,
              y: -24,
              opacity: 0.9,
              ease: "none",
              scrollTrigger: {
                trigger: closingSection,
                start: "top bottom",
                end: "bottom bottom",
                scrub: 1.2,
              },
            });
          }
        }
      }

      // ── 2. DASHBOARD EXPERIENCE ────────────────────────────────────────────
      const dashboard = document.querySelector(".main.dashboard-home");
      if (dashboard) {
        // Entrance sequence
        const dashTl = gsap.timeline({ defaults: { duration: 0.45, ease: "power2.out" } });
        dashTl
          .fromTo(visible(".search, .welcome, .dashboard-stats", dashboard), { autoAlpha: 0, y: 10 }, { autoAlpha: 1, y: 0, stagger: 0.045, clearProps: "opacity,visibility,transform" })
          .fromTo(visible(".clouds-section, .overview-card, .task-overview", dashboard), { autoAlpha: 0, y: 12 }, { autoAlpha: 1, y: 0, stagger: 0.055, clearProps: "opacity,visibility,transform" }, "<0.12");

        entrance(visible(".cloud-card-link", dashboard), { duration: 0.38, stagger: 0.035 });

        // Continuous scroll-linked depth when scrolling dashboard
        if (ScrollTrigger && isDesktop) {
          const welcomeCard = dashboard.querySelector(".welcome");
          const weatherCard = dashboard.querySelector(".welcome-weather-card");
          const statsCards = visible(".stat-card", dashboard);

          if (welcomeCard) {
            gsap.to(welcomeCard, {
              y: -12,
              ease: "none",
              scrollTrigger: {
                trigger: welcomeCard,
                start: "top top+=80",
                end: "bottom top",
                scrub: 0.7,
              },
            });
          }

          if (weatherCard) {
            gsap.to(weatherCard, {
              y: -24,
              ease: "none",
              scrollTrigger: {
                trigger: welcomeCard || weatherCard,
                start: "top top+=80",
                end: "bottom top",
                scrub: 1.1,
              },
            });
          }

          if (statsCards.length) {
            statsCards.forEach((card, idx) => {
              gsap.to(card, {
                y: -(6 + idx * 4),
                ease: "none",
                scrollTrigger: {
                  trigger: card,
                  start: "top bottom",
                  end: "bottom top",
                  scrub: 0.8,
                },
              });
            });
          }
        }
      }

      // ── 3. AUTHENTICATION PAGES ───────────────────────────────────────────
      const authCard = document.querySelector(".auth-card");
      if (authCard) {
        entrance([authCard], { duration: 0.4, y: 8 });

        const storyNotes = visible(".auth-story-notes > div");
        if (storyNotes.length && isDesktop && ScrollTrigger) {
          storyNotes.forEach((note, i) => {
            gsap.to(note, {
              y: -(14 + i * 12),
              ease: "none",
              scrollTrigger: {
                trigger: document.body,
                start: "top top",
                end: "bottom bottom",
                scrub: 0.8 + i * 0.3,
              },
            });
          });
        }
      }

      // ── 4. OTHER APPLICATION PAGES (Editor, Admin, Owner) ─────────────────
      const otherContent = document.querySelector(".content, .editor-container, .role-dashboard-container");
      if (otherContent) {
        entrance([otherContent], { duration: 0.42, y: 10 });
      }
    }
  );

  // Refresh ScrollTrigger calculations after DOM fully settles
  if (typeof window !== "undefined") {
    window.addEventListener("load", () => {
      if (ScrollTrigger) {
        ScrollTrigger.refresh();
      }
    });
  }
})();
