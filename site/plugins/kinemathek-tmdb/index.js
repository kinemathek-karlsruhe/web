// Kinemathek TMDB lookup — Panel field.
//
// A thin UI: all TMDB access, caching and privacy enforcement live in the PHP
// plugin (src/Client.php) behind the auth-protected API routes. This component
// only: reads the film title, GETs /search, shows multiple candidates (editor
// confirms — never auto-accept), POSTs the chosen id to /apply. Thumbnails come
// from the first-party proxy route, so the browser never contacts TMDB.
//
// Inline `template` works without a build step (Kirby ships the Vue compiler
// for plugins). For production you may later bundle with kirbyup.

panel.plugin("kinemathek/tmdb", {
  fields: {
    tmdblookup: {
      props: {
        label: String,
        titleField: { type: String, default: "title" },
      },
      data() {
        return {
          query: "",
          candidates: [],
          loading: false,
          message: null,
          mode: "fill-empty", // or "overwrite"
        };
      },
      created() {
        const content = this.$panel?.view?.props?.content;
        const title = content && content[this.titleField];
        if (title) this.query = title;
      },
      methods: {
        async search() {
          this.loading = true;
          this.message = null;
          try {
            const res = await this.$api.get("kinemathek/tmdb/search", {
              query: this.query,
            });
            if (res.status === "error") {
              this.message = res.message;
              this.candidates = [];
            } else {
              this.candidates = res.candidates || [];
              if (this.candidates.length === 0) {
                this.message =
                  "Kein TMDB-Treffer. Felder bitte manuell ausfüllen " +
                  "(Studierenden-/Experimentalfilme fehlen oft bei TMDB).";
              }
            }
          } catch (e) {
            this.message = String(e);
          } finally {
            this.loading = false;
          }
        },
        async apply(candidate) {
          this.loading = true;
          this.message = null;
          try {
            const pageId = this.$panel?.view?.props?.id;
            const res = await this.$api.post("kinemathek/tmdb/apply", {
              id: candidate.id,
              page: pageId,
              mode: this.mode,
            });
            if (res.status === "ok") {
              this.message =
                "Übernommen: " + (res.applied || []).join(", ") +
                (res.locked ? " (gesperrt: nur leere Felder gefüllt)" : "");
              this.$panel.view.reload();
            } else {
              this.message = res.message || res.status;
            }
          } catch (e) {
            this.message = String(e);
          } finally {
            this.loading = false;
          }
        },
      },
      template: `
        <k-field :label="label" class="k-tmdblookup-field">
          <k-input v-model="query" type="text" :placeholder="'Filmtitel…'" />
          <div class="k-tmdblookup-controls">
            <k-button icon="search" variant="filled" :disabled="loading" @click="search">
              TMDB durchsuchen
            </k-button>
            <k-select-input
              v-model="mode"
              :empty="false"
              :options="[
                { value: 'fill-empty', text: 'Nur leere Felder füllen' },
                { value: 'overwrite', text: 'Überschreiben (gesperrt bei manuell kuratiert)' }
              ]"
            />
          </div>
          <p v-if="message" class="k-tmdblookup-message">{{ message }}</p>
          <ul class="k-tmdblookup-results">
            <li v-for="c in candidates" :key="c.id">
              <img v-if="c.thumb" :src="c.thumb" width="60" loading="lazy" alt="" />
              <div>
                <strong>{{ c.title }} <em v-if="c.year">({{ c.year }})</em></strong>
                <small v-if="c.original_title && c.original_title !== c.title">
                  {{ c.original_title }}
                </small>
                <p>{{ c.overview }}</p>
                <k-button icon="check" size="xs" @click="apply(c)">Diesen Treffer übernehmen</k-button>
              </div>
            </li>
          </ul>
        </k-field>
      `,
    },
  },
});
