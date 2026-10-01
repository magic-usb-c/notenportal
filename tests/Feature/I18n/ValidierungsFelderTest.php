<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Jedes validierte Feld hat in lang/{de,en}/validation.php einen Namen. Sonst lautet die Meldung
 * «Das Feld lernende.0.vorname ist erforderlich.» statt «Das Feld Vorname ist erforderlich.».
 * Gesammelt werden die literalen Schlüssel aus validate(), validateWithBag(), Validator::make()
 * sowie aus allen Methoden regeln()/rules()/…Regeln() in app/.
 */
class ValidierungsFelderTest extends TestCase
{
    /** Felder, die nie in einer Meldung an eine Person stehen (versteckt, vom Skript gesetzt). */
    private const array INTERN = [
        'feed_id', 'js_fehler', 'p', 'route_name', 'technik', 'token', 'viewport', 'zielwert',
        'zeilen.*.element', 'zeilen.*.fach_id', 'zeilen.*.modul_id', 'zeilen.*.typ',
    ];

    #[Test]
    public function jedes_validierte_feld_hat_einen_namen(): void
    {
        $basis = dirname(__DIR__, 3);
        $fehlend = [];

        foreach (['de', 'en'] as $sprache) {
            $namen = (require "{$basis}/lang/{$sprache}/validation.php")['attributes'];
            foreach (self::felder($basis.'/app') as $feld => $ort) {
                if (! in_array($feld, self::INTERN, true) && blank($namen[$feld] ?? null)) {
                    $fehlend[] = "{$sprache}: {$feld} ({$ort})";
                }
            }
        }

        $this->assertSame([], $fehlend, "Ohne Feldnamen in validation.attributes:\n".implode("\n", $fehlend));
    }

    #[Test]
    public function keine_namen_fuer_felder_die_es_nicht_gibt(): void
    {
        $basis = dirname(__DIR__, 3);
        $felder = array_keys(self::felder($basis.'/app'));

        foreach (['de', 'en'] as $sprache) {
            $namen = array_keys((require "{$basis}/lang/{$sprache}/validation.php")['attributes']);
            $this->assertSame([], array_values(array_diff($namen, $felder)), "Verwaiste Feldnamen in {$sprache}/validation.php");
        }
    }

    #[Test]
    public function beide_sprachen_kennen_dieselben_felder(): void
    {
        $basis = dirname(__DIR__, 3);
        $de = array_keys((require "{$basis}/lang/de/validation.php")['attributes']);
        $en = array_keys((require "{$basis}/lang/en/validation.php")['attributes']);
        sort($de);
        sort($en);

        $this->assertSame($de, $en);
    }

    /** @return array<string, string> Feld => erste Fundstelle (Datei:Zeile) */
    private static function felder(string $verzeichnis): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $felder = [];

        foreach ((new Finder)->files()->in($verzeichnis)->name('*.php') as $datei) {
            $baum = (new NodeTraverser(new NameResolver))->traverse($parser->parse($datei->getContents()) ?? []);
            $ort = $datei->getRelativePathname();

            foreach ($finder->findInstanceOf($baum, Node\Stmt\ClassLike::class) as $klasse) {
                $name = $klasse->namespacedName?->toString() ?? '';

                foreach ($finder->findInstanceOf($klasse->stmts, Node\FunctionLike::class) as $funktion) {
                    $stmts = $funktion->getStmts() ?? [];
                    $quellen = [];

                    foreach ($finder->find($stmts, fn (Node $n) => self::istValidierung($n)) as $aufruf) {
                        $index = $aufruf instanceof Node\Expr\StaticCall || $aufruf->name->name === 'validateWithBag' ? 1 : 0;
                        $quellen[] = $aufruf->args[$index]->value ?? null;
                    }
                    if ($funktion instanceof Node\Stmt\ClassMethod && self::istRegelMethode($funktion->name->name)) {
                        foreach ($finder->findInstanceOf($stmts, Node\Stmt\Return_::class) as $rueckgabe) {
                            $quellen[] = $rueckgabe->expr;
                        }
                    }

                    // Regeln in einer Variablen: alle Zuweisungen an sie in derselben Funktion
                    foreach (array_filter($quellen) as $quelle) {
                        foreach ($finder->findInstanceOf([$quelle], Node\Expr\Variable::class) as $variable) {
                            $zuweisungen = $finder->find($stmts, fn (Node $n) => ($n instanceof Node\Expr\Assign || $n instanceof Node\Expr\AssignOp\Plus)
                                && self::variablenName($n->var) === $variable->name);
                            foreach ($zuweisungen as $zuweisung) {
                                $quellen[] = $zuweisung->expr;
                                if ($zuweisung->var instanceof Node\Expr\ArrayDimFetch && ($schluessel = self::schluessel($zuweisung->var->dim, $name)) !== null) {
                                    $felder[$schluessel] ??= $ort.':'.$zuweisung->getStartLine();
                                }
                            }
                        }
                    }

                    foreach (array_filter($quellen) as $quelle) {
                        foreach (self::schluesselIn($quelle, $name) as [$schluessel, $zeile]) {
                            $felder[$schluessel] ??= $ort.':'.$zeile;
                        }
                    }
                }
            }
        }

        ksort($felder);

        return $felder;
    }

    /**
     * Schlüssel der äusseren Arrays eines Ausdrucks (die Regelliste je Feld bleibt aussen vor). Ein Aufruf
     * Klasse::regeln('präfix') mit literalen Argumenten wird ausgeführt: die Regeln sind reine Funktionen.
     *
     * @return list<array{string, int}>
     */
    private static function schluesselIn(Node $n, string $klasse): array
    {
        if ($n instanceof Node\Expr\Array_) {
            $treffer = [];
            foreach ($n->items as $eintrag) {
                if ($eintrag?->unpack) {
                    array_push($treffer, ...self::schluesselIn($eintrag->value, $klasse));
                } elseif ($eintrag?->key !== null && ($schluessel = self::schluessel($eintrag->key, $klasse)) !== null) {
                    $treffer[] = [$schluessel, $eintrag->getStartLine()];
                }
            }

            return $treffer;
        }
        if ($n instanceof Node\Expr\StaticCall && $n->name instanceof Node\Identifier && self::istRegelMethode($n->name->name)
            && $n->class instanceof Node\Name && array_filter($n->args, fn ($a) => ! $a->value instanceof Node\Scalar\String_) === []) {
            $ziel = in_array($n->class->toString(), ['self', 'static'], true) ? $klasse : $n->class->toString();
            $methode = [$ziel, $n->name->name];
            if (is_callable($methode)) {
                return array_map(fn ($s) => [(string) $s, $n->getStartLine()], array_keys($methode(...array_map(fn ($a) => $a->value->value, $n->args))));
            }

            return [];
        }
        if ($n instanceof Node\Expr\Closure || $n instanceof Node\Expr\ArrowFunction) {
            return [];
        }

        $treffer = [];
        foreach ($n->getSubNodeNames() as $name) {
            foreach (is_array($n->$name) ? $n->$name : [$n->$name] as $kind) {
                if ($kind instanceof Node) {
                    array_push($treffer, ...self::schluesselIn($kind, $klasse));
                }
            }
        }

        return $treffer;
    }

    /** regeln(), rules() und Teilregeln wie wertRegeln() */
    private static function istRegelMethode(string $name): bool
    {
        return (bool) preg_match('/^(regeln|rules)$|(Regeln|Rules)$/', $name);
    }

    private static function istValidierung(Node $n): bool
    {
        return ($n instanceof Node\Expr\MethodCall && $n->name instanceof Node\Identifier && in_array($n->name->name, ['validate', 'validateWithBag'], true))
            || ($n instanceof Node\Expr\StaticCall && $n->class instanceof Node\Name && $n->class->toString() === 'Illuminate\Support\Facades\Validator'
                && $n->name instanceof Node\Identifier && $n->name->name === 'make');
    }

    private static function variablenName(Node $n): ?string
    {
        while ($n instanceof Node\Expr\ArrayDimFetch) {
            $n = $n->var;
        }

        return $n instanceof Node\Expr\Variable && is_string($n->name) ? $n->name : null;
    }

    /** Literal, Klassenkonstante oder "präfix.{$x}.feld" (dann mit * statt Variable). */
    private static function schluessel(?Node $n, string $klasse): ?string
    {
        if ($n instanceof Node\Scalar\String_) {
            return $n->value;
        }
        if ($n instanceof Node\Expr\ClassConstFetch && $n->class instanceof Node\Name && $n->name instanceof Node\Identifier) {
            $ziel = in_array($n->class->toString(), ['self', 'static'], true) ? $klasse : $n->class->toString();
            $konstante = $ziel.'::'.$n->name->name;

            return defined($konstante) && is_string(constant($konstante)) ? constant($konstante) : null;
        }
        if ($n instanceof Node\Scalar\InterpolatedString) {
            return implode('', array_map(fn (Node $teil) => $teil instanceof Node\InterpolatedStringPart ? $teil->value : '*', $n->parts));
        }

        return null;
    }
}
