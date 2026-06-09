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
//   k-field, k-text-input, k-button, k-button-group, k-toggles-input,
//   k-image, k-icon (type="loader" for the spinner — there is no k-loader in
//   Kirby 5), k-empty, k-box, k-headline, k-text, k-bubble.

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
          // id of the candidate currently being applied (null = none).
          applyingId: null,
          mode: "fill-empty", // or "overwrite"
          error: null, // string | null — hard error (network / API).
          notice: null, // string | null — soft success / info after apply.
          locked: null, // last apply's locked flag, for an accurate hint.
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
        modeOptions() {
          return [
            { value: "fill-empty", text: "Nur Leeres füllen" },
            { value: "overwrite", text: "Überschreiben" },
          ];
        },
        modeHint() {
          if (this.mode === "overwrite") {
            return "Vorhandene Felder werden ersetzt. Bei „Manuell kuratiert“ wird Überschreiben ignoriert — dann nur leere Felder.";
          }
          return "Vorhandene Inhalte bleiben unangetastet; nur leere Felder werden ergänzt.";
        },
        // "Treffer" is invariant in German, so we phrase the count without
        // pretending to pluralise the noun.
        resultsHeadline() {
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

        async apply(candidate) {
          if (this.busy) {
            return;
          }
          this.error = null;
          this.notice = null;
          this.locked = null;
          this.applyingId = candidate.id;
          try {
            const pageId = this.$panel?.view?.props?.id;
            const res = await this.$api.post("kinemathek/tmdb/apply", {
              id: candidate.id,
              page: pageId,
              mode: this.mode,
            });

            if (res.status === "ok") {
              const applied = Array.isArray(res.applied) ? res.applied : [];
              this.locked = res.locked === true;
              this.notice =
                applied.length > 0
                  ? "Übernommen: " + applied.join(", ") + "."
                  : "Keine Felder geändert — vorhandene Inhalte blieben erhalten.";
              // Persist & re-read the page so the new field values render.
              this.$panel.view.reload();
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
          }
        },

        reset() {
          this.candidates = [];
          this.lastQuery = "";
          this.searched = false;
          this.error = null;
          this.notice = null;
          this.locked = null;
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

            <!-- Search bar -->
            <div class="k-tmdblookup-search">
              <k-text-input
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
              </k-button-group>
            </div>

            <!-- Apply mode -->
            <div class="k-tmdblookup-mode">
              <k-toggles-input
                v-model="mode"
                :options="modeOptions"
                :labels="true"
                :disabled="controlsDisabled"
                aria-label="Übernahme-Modus"
              />
              <k-text class="k-tmdblookup-mode-hint">
                {{ modeHint }}
              </k-text>
            </div>

            <!-- Hard error -->
            <div
              v-if="error"
              class="k-tmdblookup-box k-tmdblookup-box--negative"
              role="alert"
            >
              <k-icon type="alert" />
              <span>{{ error }}</span>
            </div>

            <!-- Soft success after apply -->
            <div
              v-if="notice"
              class="k-tmdblookup-box"
              :class="locked ? 'k-tmdblookup-box--info' : 'k-tmdblookup-box--positive'"
              role="status"
            >
              <k-icon :type="locked ? 'lock' : 'check'" />
              <span>
                {{ notice }}
                <template v-if="locked">
                  <br />
                  <span class="k-tmdblookup-box-sub">Manuell kuratiert: nur leere Felder wurden gefüllt.</span>
                </template>
              </span>
            </div>

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
              class="k-tmdblookup-box k-tmdblookup-box--passive k-tmdblookup-idle"
            >
              <k-icon type="search" />
              <span>
                Titel oben eingeben und „Suchen“ — übernommene Treffer füllen
                Titel, Inhalt, Jahr, Laufzeit, Regie, Besetzung und Poster.
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
                  v-for="c in candidates"
                  :key="c.id"
                  class="k-tmdblookup-card"
                  :class="{
                    'k-tmdblookup-card--applying': applyingId === c.id,
                    'k-tmdblookup-card--dimmed': applyingId !== null && applyingId !== c.id,
                  }"
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
                        :loading="applyingId === c.id"
                        :disabled="controlsDisabled"
                        :aria-label="'Diesen Treffer übernehmen: ' + (c.title || 'unbekannt')"
                        @click="apply(c)"
                      >
                        {{ applyingId === c.id ? 'Übernehme …' : 'Übernehmen' }}
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

          </div>
        </k-field>
      `,
    },
  },
});
