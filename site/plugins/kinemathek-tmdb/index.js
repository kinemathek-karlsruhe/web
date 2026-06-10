// Kinemathek TMDB lookup — Panel field.
//
// A thin UI: all TMDB access, caching and privacy enforcement live in the PHP
// plugin (src/Client.php) behind the auth-protected API routes. This component
// only: reads the film title, GETs /search, shows multiple candidates (the
// editor confirms — never auto-accept), POSTs the chosen id to /apply.
// Thumbnails come from the first-party proxy route, so the browser never
// contacts TMDB.
//
// Inline `template` works without a build step (Kirby ships the Vue compiler
// for plugins). No bundler is introduced here on purpose.
//
// UI-kit components used (all verified against Kirby 5.4 panel/dist + docs):
//   k-field, k-text-input, k-button, k-button-group, k-image, k-icon
//   (type="loader" for the spinner — there is no k-loader in Kirby 5),
//   k-empty, k-box, k-headline, k-text, k-bubble.

panel.plugin("kinemathek/tmdb", {
  fields: {
    tmdblookup: {
      props: {
        // Standard Kirby field props (passed through from the blueprint).
        label: String,
        help: String,
        disabled: Boolean,
        required: Boolean,
        // Custom prop: which content field holds the film title.
        titleField: { type: String, default: "title" },
      },
      data() {
        return {
          query: "",
          // The query string of the last successful search, echoed in the
          // results headline so the editor can see what was matched.
          lastQuery: "",
          candidates: [],
          // searched=false → idle state; true → we have run at least one search.
          searched: false,
          searching: false,
          // id + mode of the candidate currently being applied (null = none).
          applyingId: null,
          applyingMode: null, // "fill-empty" | "overwrite" | null
          error: null, // string | null — hard error (network / API).
          notice: null, // string | null — soft success / info after apply.
          locked: null, // last apply's locked flag, for an accurate hint.
          // true when fields applied but an image download failed/was partial
          // — the notice then renders as a warning, not a green success.
          assetWarning: false,
          // true while the editor explicitly searches although the page is
          // already linked ("Lösen & neu suchen") — back via "Abbrechen" or a
          // successful apply.
          searchMode: false,
          // set on a successful apply so the held bar fills immediately,
          // independent of when the reloaded view props arrive.
          applied: null, // { id, title, year } | null
        };
      },
      computed: {
        // Disable interactive controls while any request is in flight or the
        // whole field is disabled via blueprint.
        busy() {
          return this.searching || this.applyingId !== null;
        },
        controlsDisabled() {
          return this.disabled || this.busy;
        },
        // ── Linked state ─────────────────────────────────────────────────
        // Once a movie has been applied, the SEARCH BAR holds it as
        // "Titel (Jahr) – Regie – TMDB 123" until the editor explicitly
        // releases it via "Lösen & neu suchen". Source of truth is the page's
        // saved tmdbId; `applied` bridges the moment between apply and the
        // view reload.
        pageContent() {
          return this.$panel?.view?.props?.content ?? {};
        },
        pageLinkedId() {
          const id = parseInt(
            this.pageContent.tmdbid ?? this.pageContent.tmdbId,
            10
          );
          return id > 0 ? id : 0;
        },
        // `applied` (the just-confirmed candidate) wins over the page content,
        // which stays stale until the view reload lands; apply() clears
        // `applied` after the reload, making the page authoritative again.
        linkedId() {
          if (this.applied && this.applied.id > 0) {
            return this.applied.id;
          }
          return this.pageLinkedId;
        },
        linked() {
          return this.linkedId > 0;
        },
        // The search bar holds the linked movie unless the editor released it.
        held() {
          return this.linked && !this.searchMode;
        },
        linkedTitle() {
          if (this.applied && this.applied.title) {
            return this.applied.title;
          }
          const title = this.pageContent[this.titleField];
          return title ? String(title) : "Ohne Titel";
        },
        linkedYear() {
          if (this.applied) {
            return this.applied.year || null;
          }
          return this.pageContent.year || null;
        },
        linkedDirectors() {
          // Unknown for a just-applied candidate; reappears after the reload.
          if (this.applied) {
            return "";
          }
          const directors = this.pageContent.directors;
          if (!Array.isArray(directors)) {
            return "";
          }
          return directors
            .map((row) => row && row.name)
            .filter(Boolean)
            .join(", ");
        },
        // What the held search bar displays: Titel (Jahr) – Regie – TMDB 123.
        // The director comes from the page content, so it appears once the
        // post-apply reload has landed.
        heldText() {
          let text = this.linkedTitle;
          if (this.linkedYear) {
            text += " (" + this.linkedYear + ")";
          }
          if (this.linkedDirectors) {
            text += " – " + this.linkedDirectors;
          }
          return text + " – TMDB " + this.linkedId;
        },

        // Notice presentation: locked beats warning beats clean success.
        // The value is a Kirby data-theme name — the Panel's [data-theme]
        // rules then supply fully light/dark-adaptive --theme-color-* tokens.
        noticeTheme() {
          if (this.locked) return "info";
          if (this.assetWarning) return "warning";
          return "positive";
        },
        noticeIcon() {
          if (this.locked) return "lock";
          if (this.assetWarning) return "alert";
          return "check";
        },
        // While a candidate is being applied, the list collapses to just that
        // card — the moment of choice is over, the rest is noise.
        visibleCandidates() {
          if (this.applyingId !== null) {
            return this.candidates.filter((c) => c.id === this.applyingId);
          }
          return this.candidates;
        },
        // "Treffer" is invariant in German, so we phrase the count without
        // pretending to pluralise the noun.
        resultsHeadline() {
          if (this.applyingId !== null) {
            return "Treffer wird übernommen …";
          }
          const n = this.candidates.length;
          const what = n === 1 ? "1 Treffer" : n + " Treffer";
          if (this.lastQuery) {
            return what + " für „" + this.lastQuery + "“ — richtige Fassung wählen";
          }
          return what + " — richtige Fassung wählen";
        },
      },
      created() {
        // Pre-fill the query from the current film title.
        const content = this.$panel?.view?.props?.content;
        const title = content && content[this.titleField];
        if (title) {
          this.query = String(title);
        }
      },
      methods: {
        async search() {
          const query = (this.query || "").trim();
          this.error = null;
          this.notice = null;
          this.locked = null;
          this.assetWarning = false;

          if (query === "") {
            this.error = "Bitte einen Filmtitel eingeben.";
            this.candidates = [];
            this.searched = true;
            return;
          }

          this.searching = true;
          try {
            const res = await this.$api.get("kinemathek/tmdb/search", {
              query,
            });
            if (res.status === "error") {
              this.error =
                res.message ||
                "Die TMDB-Suche ist fehlgeschlagen. Bitte später erneut versuchen.";
              this.candidates = [];
              this.lastQuery = "";
            } else {
              this.candidates = Array.isArray(res.candidates)
                ? res.candidates
                : [];
              this.lastQuery = query;
            }
          } catch (e) {
            this.error =
              "Die TMDB-Suche ist fehlgeschlagen: " + this.errorText(e);
            this.candidates = [];
          } finally {
            this.searching = false;
            this.searched = true;
          }
        },

        async apply(candidate, mode) {
          if (this.busy) {
            return;
          }
          this.error = null;
          this.notice = null;
          this.locked = null;
          this.assetWarning = false;
          this.applyingId = candidate.id;
          this.applyingMode = mode;
          try {
            const pageId = this.$panel?.view?.props?.id;
            const res = await this.$api.post("kinemathek/tmdb/apply", {
              id: candidate.id,
              page: pageId,
              mode: mode,
            });

            if (res.status === "ok") {
              const applied = Array.isArray(res.applied) ? res.applied : [];
              // Hold the chosen movie in the search bar until the editor
              // releases it via "Lösen & neu suchen".
              this.applied = {
                id: candidate.id,
                title: candidate.title || null,
                year: candidate.year || null,
              };
              this.searchMode = false;
              this.locked = res.locked === true;
              this.assetWarning =
                res.poster === "failed" ||
                res.stills === "failed" ||
                (res.stills === "attached" &&
                  res.stillsTotal > 0 &&
                  res.stillsCount < res.stillsTotal);
              // Per-language sync: the apply route also writes the
              // translatable fields (title/synopsis/genre) into every
              // non-default content language, fetched from TMDB in that
              // language — surface those in the notice as e.g. "EN: title, …".
              const translations = res.appliedTranslations || {};
              const translated = Object.keys(translations)
                .filter(
                  (code) =>
                    Array.isArray(translations[code]) &&
                    translations[code].length > 0
                )
                .map(
                  (code) =>
                    code.toUpperCase() + ": " + translations[code].join(", ")
                );
              const parts = [
                applied.length > 0
                  ? "Übernommen: " + applied.join(", ") + "."
                  : "Keine Felder geändert — vorhandene Inhalte blieben erhalten.",
                translated.length > 0
                  ? "Übersetzungen — " + translated.join(" · ") + "."
                  : "",
                this.posterText(res.poster),
                this.stillsText(res.stills, res.stillsCount, res.stillsTotal),
              ];
              this.notice = parts.filter(Boolean).join(" ");
              // Persist & re-read the page so the new field values render.
              // Once the reload lands, the page content is authoritative again
              // and the `applied` bridge is dropped (covers fill-empty keeping
              // an existing link as much as a normal re-link).
              Promise.resolve(this.$panel.view.reload())
                .catch(() => {})
                .then(() => {
                  this.applied = null;
                });
            } else if (res.status === "locked") {
              this.error =
                res.message ||
                "Dieser Film ist manuell kuratiert — Überschreiben ist gesperrt.";
            } else {
              this.error =
                res.message ||
                "Übernehmen fehlgeschlagen. Bitte erneut versuchen.";
            }
          } catch (e) {
            this.error = "Übernehmen fehlgeschlagen: " + this.errorText(e);
          } finally {
            this.applyingId = null;
            this.applyingMode = null;
          }
        },

        reset() {
          this.candidates = [];
          this.lastQuery = "";
          this.searched = false;
          this.error = null;
          this.notice = null;
          this.locked = null;
          this.assetWarning = false;
        },

        // "Lösen & neu suchen": clears the held bar back to an editable
        // search input. Nothing on the page changes until a new match is
        // applied — Abbrechen returns to the held movie.
        enterSearchMode() {
          this.reset();
          this.searchMode = true;
          const title = this.pageContent[this.titleField];
          if (title) {
            this.query = String(title);
          }
        },
        exitSearchMode() {
          this.reset();
          this.searchMode = false;
        },

        // Human messages for the per-asset outcome the apply route reports.
        // "skipped" (no file permission / not requested) stays silent.
        posterText(status) {
          switch (status) {
            case "attached":
              return "Poster übernommen.";
            case "kept":
              return "Poster unverändert (war schon vorhanden).";
            case "none":
              return "Kein Poster bei TMDB.";
            case "failed":
              return "Poster-Download fehlgeschlagen — bitte erneut versuchen.";
            default:
              return "";
          }
        },
        stillsText(status, count, total) {
          switch (status) {
            case "attached":
              if (total > 0 && count < total) {
                return (
                  count +
                  " von " +
                  total +
                  " Szenenbildern übernommen — Rest fehlgeschlagen, bitte erneut versuchen."
                );
              }
              return (
                (count === 1 ? "1 Szenenbild" : count + " Szenenbilder") +
                " übernommen."
              );
            case "kept":
              return "Szenenbilder unverändert (waren schon vorhanden).";
            case "none":
              return "Keine Szenenbilder bei TMDB.";
            case "failed":
              return "Szenenbild-Download fehlgeschlagen — bitte erneut versuchen.";
            default:
              return "";
          }
        },

        errorText(e) {
          if (e == null) return "Unbekannter Fehler.";
          if (typeof e === "string") return e;
          return e.message || String(e);
        },

        // Only show the original title when it actually adds information.
        showOriginal(c) {
          return (
            c.original_title &&
            c.original_title.trim() !== "" &&
            c.original_title.trim() !== (c.title || "").trim()
          );
        },
      },
      template: `
        <k-field
          :label="label"
          :help="help"
          :required="required"
          :disabled="disabled"
          class="k-tmdblookup-field"
        >
          <div class="k-tmdblookup">

            <!-- Search bar. When a movie is linked, the bar HOLDS it
                 ("Titel (Jahr) – Regie – TMDB 123") until released. -->
            <div class="k-tmdblookup-search">
              <k-text-input
                v-if="held"
                :value="heldText"
                type="text"
                name="tmdb-held"
                icon="check"
                :disabled="true"
                :aria-label="'Verknüpfter Film: ' + heldText"
                class="k-tmdblookup-held"
              />
              <k-text-input
                v-else
                v-model="query"
                type="text"
                name="tmdb-query"
                icon="search"
                autocomplete="off"
                :disabled="controlsDisabled"
                :aria-label="'Filmtitel für die TMDB-Suche'"
                placeholder="Filmtitel suchen…"
                @submit="search"
              />
              <k-button-group class="k-tmdblookup-search-buttons">
                <k-button
                  v-if="held"
                  icon="cancel"
                  variant="filled"
                  size="sm"
                  :disabled="controlsDisabled"
                  @click="enterSearchMode"
                >
                  Lösen & neu suchen
                </k-button>
                <template v-else>
                  <k-button
                    icon="search"
                    variant="filled"
                    theme="notice"
                    size="sm"
                    :disabled="controlsDisabled"
                    @click="search"
                  >
                    Suchen
                  </k-button>
                  <k-button
                    v-if="searched"
                    icon="refresh"
                    variant="filled"
                    size="sm"
                    :disabled="busy"
                    @click="reset"
                  >
                    Zurücksetzen
                  </k-button>
                  <k-button
                    v-if="linked"
                    icon="undo"
                    variant="filled"
                    size="sm"
                    :disabled="busy"
                    @click="exitSearchMode"
                  >
                    Abbrechen
                  </k-button>
                </template>
              </k-button-group>
            </div>

            <!-- Hard error -->
            <div
              v-if="error"
              class="k-tmdblookup-box"
              data-theme="negative"
              role="alert"
            >
              <k-icon type="alert" />
              <span>{{ error }}</span>
            </div>

            <!-- Soft success / warning after apply -->
            <div
              v-if="notice"
              class="k-tmdblookup-box"
              :data-theme="noticeTheme"
              role="status"
            >
              <k-icon :type="noticeIcon" />
              <span>
                {{ notice }}
                <template v-if="locked">
                  <br />
                  <span class="k-tmdblookup-box-sub">Manuell kuratiert: nur leere Felder wurden gefüllt.</span>
                </template>
              </span>
            </div>

            <!-- Search states + results: hidden while the bar holds a movie -->
            <template v-if="!held">

            <!-- Loading -->
            <div
              v-if="searching"
              class="k-tmdblookup-state k-tmdblookup-loading"
              aria-live="polite"
            >
              <k-icon type="loader" class="k-tmdblookup-spinner" />
              <k-text>TMDB wird durchsucht …</k-text>
            </div>

            <!-- Idle prompt (before first search) -->
            <div
              v-else-if="!searched"
              class="k-tmdblookup-box k-tmdblookup-idle"
              data-theme="passive"
            >
              <k-icon type="search" />
              <span>
                Titel oben eingeben und „Suchen“ — übernommene Treffer füllen
                Titel, Inhalt, Jahr, Laufzeit, Regie, Besetzung, Poster und
                Szenenbilder.
                <template v-if="linked">
                  Die bestehende Verknüpfung bleibt erhalten, bis ein neuer
                  Treffer übernommen wird.
                </template>
              </span>
            </div>

            <!-- No match / empty. We pass the guidance both into the k-empty
                 slot AND as the text prop, so it shows regardless of which
                 the installed Kirby build renders. -->
            <k-empty
              v-else-if="!searching && candidates.length === 0 && !error"
              icon="search"
              text="Kein TMDB-Treffer. Studierenden-, Experimental- und Archivfilme fehlen dort oft — die Felder bitte manuell ausfüllen."
              class="k-tmdblookup-empty"
            >
              Kein TMDB-Treffer. Studierenden-, Experimental- und Archivfilme
              fehlen dort oft — die Felder bitte manuell ausfüllen.
            </k-empty>

            <!-- Results -->
            <div v-else-if="candidates.length" class="k-tmdblookup-results">
              <k-headline class="k-tmdblookup-results-head" aria-live="polite">
                {{ resultsHeadline }}
              </k-headline>
              <ul class="k-tmdblookup-list">
                <li
                  v-for="c in visibleCandidates"
                  :key="c.id"
                  class="k-tmdblookup-card"
                  :class="{ 'k-tmdblookup-card--applying': applyingId === c.id }"
                >
                  <figure class="k-tmdblookup-thumb">
                    <k-image
                      v-if="c.thumb"
                      :src="c.thumb"
                      back="pattern"
                      :cover="true"
                      ratio="2/3"
                      :alt="'Poster: ' + (c.title || 'unbekannt')"
                    />
                    <span v-else class="k-tmdblookup-thumb-fallback" aria-hidden="true">
                      <k-icon type="image" />
                    </span>
                  </figure>

                  <div class="k-tmdblookup-body">
                    <p class="k-tmdblookup-title">
                      <span class="k-tmdblookup-title-text">{{ c.title || 'Ohne Titel' }}</span>
                      <k-bubble
                        v-if="c.year"
                        :text="String(c.year)"
                        class="k-tmdblookup-year"
                      />
                    </p>
                    <p v-if="showOriginal(c)" class="k-tmdblookup-original">
                      Original: {{ c.original_title }}
                    </p>
                    <p v-if="c.overview" class="k-tmdblookup-overview">
                      {{ c.overview }}
                    </p>
                    <p v-else class="k-tmdblookup-overview k-tmdblookup-overview--empty">
                      Keine Inhaltsangabe bei TMDB.
                    </p>

                    <div class="k-tmdblookup-actions">
                      <k-button
                        icon="check"
                        variant="filled"
                        theme="positive"
                        size="sm"
                        :loading="applyingId === c.id && applyingMode === 'fill-empty'"
                        :disabled="controlsDisabled"
                        title="Füllt nur leere Felder — vorhandene Inhalte bleiben unangetastet."
                        :aria-label="'Diesen Treffer übernehmen (nur leere Felder): ' + (c.title || 'unbekannt')"
                        @click="apply(c, 'fill-empty')"
                      >
                        {{ applyingId === c.id && applyingMode === 'fill-empty' ? 'Übernehme …' : 'Übernehmen' }}
                      </k-button>
                      <k-button
                        icon="wand"
                        variant="filled"
                        theme="notice"
                        size="sm"
                        :loading="applyingId === c.id && applyingMode === 'overwrite'"
                        :disabled="controlsDisabled"
                        title="Ersetzt auch vorhandene Felder. Bei „Manuell kuratiert“ gesperrt — dann nur leere Felder."
                        :aria-label="'Diesen Treffer übernehmen und vorhandene Felder überschreiben: ' + (c.title || 'unbekannt')"
                        @click="apply(c, 'overwrite')"
                      >
                        {{ applyingId === c.id && applyingMode === 'overwrite' ? 'Überschreibe …' : 'Überschreiben' }}
                      </k-button>
                    </div>
                  </div>
                </li>
              </ul>
              <!-- TMDB attribution (SPEC §4). Plain text, no network call. -->
              <p class="k-tmdblookup-attribution">
                Daten von The Movie Database (TMDB).
              </p>
            </div>

            </template>

          </div>
        </k-field>
      `,
    },
  },
});
