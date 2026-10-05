<?php

namespace Kinemathek;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Data\Data;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Exception\NotFoundException;
use Kirby\Exception\PermissionException;
use Kirby\Cms\Find;
use Kirby\Form\Form;
use Kirby\Panel\Field;
use Kirby\Toolkit\Str;

/**
 * Panel-Dialog „Neue Vorstellung" direkt am Film (Tab „Vorführungen", das
 * Plus der `filmshowings`-Sektion). Legt die Vorstellung wie gewohnt unter
 * program/ an — die Film↔Showing-Relation bleibt allein auf der Showing —,
 * nur ist der Film schon verknüpft und der Editor bleibt auf der Filmseite
 * (mehrere Termine eines Films lassen sich so in einem Zug anlegen).
 */
class ShowingCreateDialog
{
    /** Showing-Felder, die der Dialog abfragt — Definitionen aus showing.yml. */
    public const FIELDS = ['date', 'venue', 'subtitles'];

    protected Page $film;
    protected Page $program;

    /** @param string $filmPath Panel-Pfad der Filmseite, z. B. "pages/films+leica" */
    public function __construct(protected string $filmPath)
    {
        $this->film = Find::page(Str::after($filmPath, 'pages/'));

        if ($this->film->intendedTemplate()->name() !== 'film') {
            throw new InvalidArgumentException(message: 'Vorstellungen lassen sich nur für Filme anlegen.');
        }

        $this->program = App::instance()->page('program')
            ?? throw new NotFoundException(message: 'Die Seite „Programm (Spielplan)" fehlt.');
    }

    /** Noch nicht gespeicherte Showing — löst Feld-Props und Eingaben auf. */
    protected function model(): Page
    {
        return Page::factory([
            'slug'     => '__new__',
            'template' => 'showing',
            'model'    => 'showing',
            'parent'   => $this->program,
        ]);
    }

    protected function fields(): array
    {
        $fields = array_intersect_key(
            $this->model()->blueprint()->fields(),
            array_flip(static::FIELDS)
        );

        foreach ($fields as $name => $field) {
            $fields[$name]['width'] = '1/1';
        }

        return (new Form(fields: $fields, model: $this->model()))->fields()->toProps();
    }

    public function load(): array
    {
        return [
            'component' => 'k-form-dialog',
            'props'     => [
                'fields' => [
                    'film' => [
                        'type'  => 'info',
                        'label' => 'Film',
                        'theme' => 'passive',
                        'text'  => Str::esc($this->film->title()->value()),
                    ],
                    ...$this->fields(),
                    // Beim Absenden geht nur der Formularwert mit, nicht die
                    // Query des Dialogs — der Film reist deshalb versteckt mit.
                    'filmPath' => Field::hidden(),
                ],
                'value' => [
                    ...array_fill_keys(static::FIELDS, null),
                    'filmPath' => $this->filmPath,
                ],
                'submitButton' => 'Vorstellung anlegen',
            ],
        ];
    }

    public function submit(array $input): array
    {
        // Den ECHTEN Aufrufer prüfen, bevor unten für den Status impersoniert wird.
        if ($this->program->permissions()->can('create') !== true) {
            throw new PermissionException(message: 'Du darfst keine Vorstellungen anlegen.');
        }

        $time = strtotime((string) ($input['date'] ?? ''));
        if ($time === false) {
            throw new InvalidArgumentException(message: 'Bitte Datum und Uhrzeit angeben.');
        }

        // Gleiches Slug-Muster wie die von Hand angelegten Vorstellungen.
        $slug = $this->film->slug() . '-' . date('Ymd-Hi', $time);
        if ($this->program->findPageOrDraft($slug) !== null) {
            throw new InvalidArgumentException(
                message: 'Für diesen Film gibt es zu diesem Termin schon eine Vorstellung.'
            );
        }

        $content = Form::for($this->model())
            ->fill(input: array_intersect_key($input, array_flip(static::FIELDS)))
            ->strings(true);

        // Eine `pages`-Feld-Referenz, wie das Panel sie schreibt (YAML-Liste).
        $content['film'] = Data::encode([$this->film->uuid()?->toString() ?? $this->film->id()], 'yaml');

        $page = $this->program->createChild([
            'slug'     => $slug,
            'template' => 'showing',
            'content'  => $content,
        ]);

        // Sofort veröffentlichen — eine Vorstellung als Entwurf taucht in den
        // Listen am Film nicht auf. (Wie Kirbys eigener Dialog bei
        // `create: status`: der Status ist hier gesetzt, nicht vom Nutzer gewählt.)
        $page->kirby()->impersonate('kirby', fn () => $page->changeStatus('listed'));

        return ['event' => 'kinemathek.showing.create'];
    }
}
