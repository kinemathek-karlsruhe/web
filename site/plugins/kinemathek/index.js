// Kinemathek core — Panel-Seite der `filmshowings`-Sektion (Film → Tab
// „Vorführungen"). Kirbys Seiten-Sektion, nur dass das Plus den eigenen
// Dialog öffnet: die Vorstellung entsteht unter program/, der Film ist schon
// verknüpft (PHP: src/ShowingCreateDialog.php). No-build, wie das TMDB-Feld.

panel.plugin("kinemathek/core", {
  // Bewusst unter `components` (mit vollem Namen) statt unter `sections`:
  // dort hängt Kirby das Section-Mixin an und instanziiert dafür die
  // Basiskomponente probeweise — das wirft Konsolenfehler. k-pages-section
  // bringt das Mixin ohnehin schon mit.
  components: {
    "k-filmshowings-section": {
      extends: "k-pages-section",
      mounted() {
        this.$events.on("kinemathek.showing.create", this.reload);
      },
      destroyed() {
        this.$events.off("kinemathek.showing.create", this.reload);
      },
      methods: {
        onAdd() {
          if (this.canAdd) {
            this.$dialog("kinemathek/showings/create", {
              query: { film: this.parent },
            });
          }
        },
      },
    },
  },
});
