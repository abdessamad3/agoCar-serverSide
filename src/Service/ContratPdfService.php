<?php

namespace App\Service;

use App\Entity\Contrat;
use App\Entity\ConditionsContrat;
use App\Entity\ParametresSociete;
use App\Entity\VehicleDelivery;
use App\Entity\VehicleReturnInspection;
use ArPHP\I18N\Arabic;
use Dompdf\Dompdf;
use Dompdf\Options;

class ContratPdfService
{
    public function __construct(private string $projectDir) {}

    // ── Logo (base64-embedded for Dompdf, ENABLE_REMOTE=false) ──────────────

    private function logoHtml(?string $logoPath): string
    {
        if ($logoPath) {
            $abs = $this->projectDir . '/public' . $logoPath;
            if (file_exists($abs)) {
                $data = base64_encode((string) file_get_contents($abs));
                $mime = mime_content_type($abs) ?: 'image/png';
                return '<img src="data:' . $mime . ';base64,' . $data . '"'
                     . ' style="max-width:30mm;max-height:24mm;display:block;margin:auto;">';
            }
        }
        return '<div style="font-size:10pt;font-weight:900;color:#8b6914;text-align:center;line-height:1.2;">A.G.O</div>'
             . '<div style="font-size:7pt;font-weight:700;text-align:center;letter-spacing:1px;">RENT CAR</div>';
    }

    // ── Entry point ───────────────────────────────────────────────────────────

    public function generate(
        Contrat $contrat,
        ParametresSociete $ps,
        ConditionsContrat $cc,
        ?VehicleDelivery $delivery = null,
        ?VehicleReturnInspection $returnInspection = null
    ): string {
        $opts = new Options();
        $opts->set('ENABLE_REMOTE',           false);
        $opts->set('ENABLE_HTML5PARSER',      true);
        $opts->set('DEFAULT_PAPER_SIZE',      'a4');
        $opts->set('DEFAULT_PAPER_ORIENTATION', 'portrait');
        $opts->set('DPI',                     96);
        $opts->set('isPhpEnabled',            false);
        $opts->set('isFontSubsettingEnabled', true);
        $opts->set('defaultFont',             'dejavu sans');

        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml(
            $this->buildHtml($contrat, $ps, $delivery, $returnInspection),
            'UTF-8'
        );
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    // ── Arabic reshaping (ArPHP) ──────────────────────────────────────────────

    private ?Arabic $arInst = null;

    private function ar(): Arabic
    {
        if ($this->arInst === null) {
            $this->arInst = new Arabic();
        }
        return $this->arInst;
    }

    private function arText(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
        if (trim($decoded) === '') {
            return '';
        }
        return $this->ar()->utf8Glyphs($decoded, 10000);
    }

    private function arHtml(string $html): string
    {
        $parts = preg_split(
            '/(<(?:[^>"\']*|"[^"]*"|\'[^\']*\')*>)/',
            $html, -1, PREG_SPLIT_DELIM_CAPTURE
        );
        $out = '';
        foreach ($parts as $p) {
            $out .= ($p !== '' && $p[0] === '<') ? $p : $this->arText($p);
        }
        return $out;
    }

    // ── Generic helpers ───────────────────────────────────────────────────────

    private function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function fmtDate(?\DateTimeInterface $d): string
    {
        return $d ? $d->format('d/m/Y') : '';
    }

    private function fmtDateTime(?\DateTimeInterface $d): string
    {
        return $d ? $d->format('d/m/Y H:i') : '';
    }

    private function g(?object $obj, string $method, string $default = ''): string
    {
        if ($obj === null || !method_exists($obj, $method)) {
            return $default;
        }
        $val = $obj->$method();
        if ($val instanceof \DateTimeInterface) {
            return $val->format('d/m/Y');
        }
        return (string) ($val ?? $default);
    }

    private function chk(bool $v): string
    {
        return $v ? '&#9745;' : '&#9744;';
    }

    // ── Field helpers ─────────────────────────────────────────────────────────

    private function fld(string $label, string $value = '', int $lblPct = 40): string
    {
        $vPct = 100 - $lblPct;
        return '<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:0.9mm;">'
             . '<tr>'
             . '<td width="' . $lblPct . '%" style="font-size:5.5pt;font-weight:700;'
             . 'vertical-align:bottom;white-space:nowrap;padding-right:1mm;line-height:1.2;">'
             . $label . '&nbsp;:</td>'
             . '<td width="' . $vPct . '%" style="border-bottom:1px dotted #777;'
             . 'font-size:6pt;vertical-align:bottom;line-height:1.2;padding-left:1mm;">'
             . $value . '</td>'
             . '</tr></table>';
    }

    private function fld2(array $a, array $b): string
    {
        return '<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:0.9mm;">'
             . '<tr>'
             . '<td width="50%" style="padding-right:1.5mm;">' . $this->fldCell($a) . '</td>'
             . '<td width="50%">' . $this->fldCell($b) . '</td>'
             . '</tr></table>';
    }

    private function fldCell(array $item): string
    {
        [$lbl, $val, $lp] = [$item[0], $item[1] ?? '', $item[2] ?? 40];
        $vp = 100 - $lp;
        return '<table width="100%" cellspacing="0" cellpadding="0"><tr>'
             . '<td width="' . $lp . '%" style="font-size:5.5pt;font-weight:700;'
             . 'vertical-align:bottom;white-space:nowrap;padding-right:1mm;line-height:1.2;">'
             . $lbl . '&nbsp;:</td>'
             . '<td width="' . $vp . '%" style="border-bottom:1px dotted #777;'
             . 'font-size:6pt;vertical-align:bottom;line-height:1.2;padding-left:1mm;">'
             . $val . '</td>'
             . '</tr></table>';
    }

    private function fldSpacer(string $lbl, string $val): string
    {
        return '<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:0.9mm;">'
             . '<tr>'
             . '<td width="55%" style="border-bottom:1px dotted #777;font-size:6pt;vertical-align:bottom;">&nbsp;</td>'
             . '<td width="4%">&nbsp;</td>'
             . '<td width="18%" style="font-size:5.5pt;font-weight:700;vertical-align:bottom;'
             . 'white-space:nowrap;padding-right:1mm;line-height:1.2;">' . $lbl . '&nbsp;:</td>'
             . '<td width="23%" style="border-bottom:1px dotted #777;font-size:6pt;vertical-align:bottom;'
             . 'padding-left:1mm;">' . $val . '</td>'
             . '</tr></table>';
    }

    private function sep(): string
    {
        return '<div style="border-top:1.5px solid #000;margin:1.8mm 0;"></div>';
    }

    private function secHd(string $label): string
    {
        return '<div style="font-size:6pt;font-weight:900;text-transform:uppercase;'
             . 'letter-spacing:0.3px;margin:1.5mm 0 0.8mm;border-bottom:1px solid #000;'
             . 'padding-bottom:0.5mm;">'
             . $label . '</div>';
    }

    // ── G5 — Fuel gauge (5-segment table, Dompdf-safe — no SVG) ─────────────

    private function fuelTable(?string $level): string
    {
        $map = [
            'vide'         => 0,
            'quart'        => 1,
            'moitie'       => 2,
            'trois_quarts' => 3,
            'plein'        => 4,
        ];
        $active = $map[$level] ?? -1;
        $labels = ['0', '1/4', '1/2', '3/4', '1'];

        $cells = '';
        foreach ($labels as $i => $lbl) {
            $on     = ($i === $active);
            $bg     = $on ? '#111' : '#fff';
            $fg     = $on ? '#fff' : '#111';
            $cells .= '<td style="border:1.5px solid #444;text-align:center;'
                    . 'padding:2mm 0;background-color:' . $bg . ';color:' . $fg . ';'
                    . 'font-size:6.5pt;font-weight:bold;">' . $lbl . '</td>';
        }

        return '<div style="margin:1.5mm 0 0.5mm;">'
             . '<div style="font-size:5pt;font-weight:700;margin-bottom:0.8mm;'
             . 'text-align:center;letter-spacing:0.5px;">CARBURANT</div>'
             . '<table width="100%" cellspacing="0" cellpadding="0"'
             . ' style="border-collapse:collapse;"><tr>'
             . $cells
             . '</tr></table></div>';
    }

    // ── G6 — Car diagram (HTML table, Dompdf-safe — no SVG) ─────────────────

    private function carDiagram(): string
    {
        return '<table width="100%" cellspacing="0" cellpadding="0"'
             . ' style="border-collapse:collapse;border:1.5px solid #000;height:24mm;">'
             . '<tr>'
             . '<td width="20%" style="text-align:center;vertical-align:middle;'
             . 'font-size:5.5pt;font-weight:bold;border-right:1.5px solid #000;'
             . 'padding:2mm 1mm;">AVANT</td>'
             . '<td style="text-align:center;vertical-align:middle;">'
             . '<div style="font-size:26pt;font-weight:900;line-height:1;color:#000;">D</div>'
             . '</td>'
             . '<td width="20%" style="text-align:center;vertical-align:middle;'
             . 'font-size:5.5pt;font-weight:bold;border-left:1.5px solid #000;'
             . 'padding:2mm 1mm;">ARRI&Egrave;RE</td>'
             . '</tr></table>';
    }

    // ── Accessories row ───────────────────────────────────────────────────────

    private function accRow(array $items): string
    {
        if (empty($items)) {
            return '';
        }
        $cells = '';
        foreach ($items as $item) {
            $cells .= '<td style="font-size:5.5pt;padding-right:2.5mm;white-space:nowrap;'
                    . 'vertical-align:middle;">' . $item . '</td>';
        }
        return '<table cellspacing="0" cellpadding="0" style="margin-bottom:0.7mm;">'
             . '<tr>' . $cells . '</tr></table>';
    }

    // ── Page 1 — RECTO ────────────────────────────────────────────────────────

    private function buildHtml(
        Contrat $contrat,
        ParametresSociete $ps,
        ?VehicleDelivery $delivery,
        ?VehicleReturnInspection $returnInspection
    ): string {

        // ── Entity variables ──────────────────────────────────────────────────
        $res      = $contrat->getReservation();
        $voit     = $res?->getVoiture();
        $client   = $res?->getClient();
        $dc       = $res?->getDeuxiemeChauffeur();
        $bureau   = $res?->getBureau();
        $logoPath = $bureau?->getCompany()?->getLogo() ?? $ps->getLogoPath();

        $dateDebut = $res?->getDateDebut();
        $dateFin   = $res?->getDateFin();
        $nbJours   = ($dateDebut && $dateFin)
            ? max(1, (int) $dateDebut->diff($dateFin)->days)
            : ($contrat->getNbJoursFactures() ?? 1);

        $total          = (float) ($res?->getTotal() ?? 0);
        $montantPaye    = (float) ($res?->getMontantPaye() ?? 0);
        $montantRestant = max(0.0, $total - $montantPaye);
        $prixJour       = $contrat->getPrixParJourSnapshot() ?? $voit?->getPrixJour() ?? 0;

        $mileageOut = $delivery?->getMileageOut();
        $mileageIn  = $returnInspection?->getKilometrage();
        $kmEff      = ($mileageOut !== null && $mileageIn !== null)
            ? ((int) $mileageIn - (int) $mileageOut)
            : null;

        $fuelOut = $delivery?->getFuelLevelOut();
        $today   = (new \DateTimeImmutable())->format('d/m/Y');

        // ── ZONE A+B+C — Header ───────────────────────────────────────────────
        $arContrat = $this->arText('عقد :');
        $arKiraa   = $this->arText('كراء السيارات');

        $hdr = '<table width="100%" cellspacing="0" cellpadding="0"'
             . ' style="border:2px solid #000;border-collapse:collapse;">'
             . '<tr>'

             // A — Logo
             . '<td width="22%" style="padding:2mm 3mm;border-right:2px solid #000;'
             . 'vertical-align:middle;text-align:center;">'
             . $this->logoHtml($logoPath)
             . '</td>'

             // B — Title
             . '<td style="text-align:center;vertical-align:middle;padding:3mm 2mm;'
             . 'border-right:2px solid #000;">'
             . '<div style="font-size:14pt;font-weight:900;letter-spacing:0.5px;'
             . 'line-height:1.25;text-transform:uppercase;">Location de Voitures</div>'
             . '<div style="font-size:11pt;font-weight:900;line-height:1.25;margin-top:1mm;">'
             . $arKiraa . '</div>'
             . '</td>'

             // C — Contract meta + police notice
             . '<td width="33%" style="padding:2mm 3mm;vertical-align:top;">'
             . '<table width="100%" cellspacing="0" cellpadding="0"'
             . ' style="font-size:6pt;line-height:1.5;margin-bottom:1mm;">'
             . '<tr>'
             . '<td><b>CONTRAT&nbsp;:</b>&nbsp;' . $this->h($contrat->getNumero()) . '</td>'
             . '<td style="text-align:right;"><b>' . $arContrat . '</b></td>'
             . '</tr><tr>'
             . '<td><b>Le&nbsp;:</b>&nbsp;' . $today . '</td>'
             . '<td style="text-align:right;"><b>&agrave;&nbsp;:</b>&nbsp;'
             . $this->h($bureau?->getAdresse() ?? $contrat->getFaitA() ?? '') . '</td>'
             . '</tr></table>'
             . '<div style="font-size:4.8pt;line-height:1.35;color:#222;">'
             . 'Ce Contrat doit accompagner le v&eacute;hicule pendant toute la dur&eacute;e '
             . 'de location, afin d\'&ecirc;tre pr&eacute;sent&eacute; &agrave; toute '
             . 'r&eacute;quisition des services de police ou de gendarmerie.'
             . '</div>'
             . '</td>'

             . '</tr></table>';

        // ── ZONE D — Phone bar ────────────────────────────────────────────────
        $phoneStr = $bureau?->getTelephone() ?? '';
        $phoneBar = '<div style="text-align:center;font-size:11pt;font-weight:900;'
                  . 'padding:1.5mm 0;letter-spacing:0.5px;'
                  . 'border-left:2px solid #000;border-right:2px solid #000;'
                  . 'border-bottom:2px solid #000;">'
                  . '&#9742;&nbsp;' . $this->h($phoneStr)
                  . '</div>';

        // ── ZONE E — Address bar ──────────────────────────────────────────────
        $infoBits = [];
        if ($ps->getRc())       { $infoBits[] = 'RC&nbsp;: '   . $this->h($ps->getRc()); }
        if ($ps->getIfFiscal()) { $infoBits[] = 'IF&nbsp;: '   . $this->h($ps->getIfFiscal()); }
        if ($ps->getIce())      { $infoBits[] = 'ICE&nbsp;: '  . $this->h($ps->getIce()); }
        if ($ps->getCnss())     { $infoBits[] = 'CNSS&nbsp;: ' . $this->h($ps->getCnss()); }

        $addrBar = '<div style="border-left:2px solid #000;border-right:2px solid #000;'
                 . 'border-bottom:2px solid #000;padding:1.2mm 4mm;'
                 . 'font-size:6pt;font-weight:700;text-align:center;line-height:1.5;">'
                 . '&#9400;&nbsp;' . $this->h($bureau?->getAdresse() ?? '')
                 . (!empty($infoBits) ? '&nbsp;&nbsp;&#8212;&nbsp;&nbsp;' . implode('&nbsp;&nbsp;/&nbsp;&nbsp;', $infoBits) : '')
                 . '</div>';

        // ── ZONE F1 — Dates ───────────────────────────────────────────────────
        $L  = '';
        $L .= $this->fld('Date et heure de D&eacute;part',        $this->fmtDateTime($dateDebut));
        $L .= $this->fld('Date et heure de retour pr&eacute;vue', $this->fmtDateTime($dateFin));
        $L .= $this->fld('Dur&eacute;e total',                    $nbJours . ' jour(s)');
        $L .= $this->fld('Prolongations&nbsp;: Date &amp; Heure');
        $L .= $this->fld('Nbrs total jours Prolong&eacute;s');

        $L .= $this->sep();

        // ── ZONE F2 — Client ──────────────────────────────────────────────────
        $clientNom = trim($this->g($client, 'getNom') . ' ' . $this->g($client, 'getPrenom'));
        $dob = $this->fmtDate($client?->getDateNaissance())
             . ($this->g($client, 'getLieuNaissance')
                 ? ' - ' . $this->h($this->g($client, 'getLieuNaissance')) : '');

        $L .= $this->fld('Nom',                         $this->h($clientNom));
        $L .= $this->fld('Date et lieu de naissance',   $dob);
        $L .= $this->fld('Nationalit&eacute;',          $this->h($this->g($client, 'getNationalite')));
        $L .= $this->fld('Adresse au Maroc',            $this->h($this->g($client, 'getAdresseMaroc')));
        $L .= $this->fldSpacer('T&eacute;l',            $this->h($this->g($client, 'getTelephone')));
        $L .= $this->fld('Adresse &agrave; l\'&eacute;tranger', $this->h($this->g($client, 'getAdresseEtranger')));
        $L .= $this->fldSpacer('T&eacute;l',            $this->h($this->g($client, 'getTelephoneEtranger')));
        $L .= $this->fld('C.I.N N&deg;',               $this->h($this->g($client, 'getCin')));
        $L .= $this->fld('Permis de conduire N&deg;',  $this->h($this->g($client, 'getPermisConduite')));
        $L .= $this->fld2(
            ['D&eacute;livr&eacute; le', $this->g($client, 'getPermisDelivreLe'), 44],
            ['&agrave;',                 $this->h($this->g($client, 'getPermisDelivreA')), 22]
        );
        $L .= $this->fld('Passeport N&deg;',            $this->h($this->g($client, 'getPasseport')));
        $L .= $this->fld2(
            ['D&eacute;livr&eacute; le', $this->g($client, 'getPasseportDelivreLe'), 44],
            ['&agrave;',                 $this->h($this->g($client, 'getPasseportDelivreA')), 22]
        );

        $L .= $this->sep();
        $L .= $this->secHd('2&egrave;me CONDUCTEUR&nbsp;:');

        // ── ZONE F3 — 2nd driver ──────────────────────────────────────────────
        $dcAddr = $this->g($dc, 'getAdresse') ?: $this->g($dc, 'getAdresseMaroc');
        $dcDob  = $this->g($dc, 'getDateNaissance');

        $L .= $this->fld('Nom', $this->h($this->g($dc, 'getNom')));
        $L .= $this->fld('Date et lieu de naissance', $dcDob
            . ($this->g($dc, 'getLieuNaissance')
                ? ' - ' . $this->h($this->g($dc, 'getLieuNaissance')) : ''));
        $L .= $this->fld('Nationalit&eacute;',         $this->h($this->g($dc, 'getNationalite')));
        $L .= $this->fld('Adresse au Maroc',           $this->h($dcAddr));
        $L .= $this->fldSpacer('T&eacute;l',           $this->h($this->g($dc, 'getTelephone')));
        $L .= $this->fld('Adresse &agrave; l\'&eacute;tranger', $this->h($this->g($dc, 'getAdresseEtranger')));
        $L .= $this->fldSpacer('T&eacute;l', '');
        $L .= $this->fld('C.I.N N&deg;',              $this->h($this->g($dc, 'getCin')));
        $L .= $this->fld('Permis de conduire N&deg;', $this->h($this->g($dc, 'getPermisConduite')));
        $L .= $this->fld2(
            ['D&eacute;livr&eacute; le', $this->g($dc, 'getPermisDelivreLe'), 44],
            ['&agrave;',                 $this->h($this->g($dc, 'getPermisDelivreA')), 22]
        );
        $L .= $this->fld('Passeport N&deg;',           $this->h($this->g($dc, 'getPasseport')));
        $L .= $this->fld2(
            ['D&eacute;livr&eacute; le', $this->g($dc, 'getPasseportDelivreLe'), 44],
            ['&agrave;',                 $this->h($this->g($dc, 'getPasseportDelivreA')), 22]
        );

        // ── ZONE G1 — Vehicle ─────────────────────────────────────────────────
        $voitLabel = trim(
            $this->h($this->g($voit, 'getMarque')) . ' ' .
            $this->h($this->g($voit, 'getModele'))
        );

        $R  = '';
        $R .= $this->fld('Marque',            $voitLabel);
        $R .= $this->fld('N&deg; Immt',       $this->h($this->g($voit, 'getImmatriculation')));
        $R .= $this->fld('Lieu de Livraison', $this->h($this->g($res, 'getLieuLivraison')));
        $R .= $this->fld('Lieu de Retour',    $this->h($this->g($res, 'getLieuRetour')));
        $R .= $this->fld('Date de Retour',    $this->fmtDate($dateFin));

        $R .= $this->sep();

        // ── ZONE G2 — Km ─────────────────────────────────────────────────────
        $R .= $this->fld('Km D&eacute;part',      $mileageOut !== null ? (string) $mileageOut : '');
        $R .= $this->fld('Km R&eacute;cup&eacute;r&eacute;', $mileageIn !== null ? (string) $mileageIn : '');
        $R .= $this->fld('Km Effectu&eacute;',    $kmEff !== null ? (string) $kmEff : '');
        $R .= $this->fld('Prix Jours',
            $prixJour ? number_format((float) $prixJour, 2) . ' DH' : '');

        $R .= $this->sep();

        // ── ZONE G3 — Financial ───────────────────────────────────────────────
        $R .= $this->fld('Avance',                    number_format($montantPaye, 2) . ' DH');
        $R .= $this->fld('Reste',                     number_format($montantRestant, 2) . ' DH');
        $R .= $this->fld('Nbrs de jours factur&eacute;s',
            (string) ($contrat->getNbJoursFactures() ?? $nbJours));
        $R .= $this->fld('Total net &agrave; payer',  number_format($total, 2) . ' DH');
        $R .= $this->fld('Franchise',
            $contrat->getFranchise()
                ? number_format((float) $contrat->getFranchise(), 2) . ' DH' : '');

        $cautionOui = $contrat->isHasCaution() ? '&#9745;' : '&#9744;';
        $cautionNon = !$contrat->isHasCaution() ? '&#9745;' : '&#9744;';
        $R .= '<div style="font-size:6pt;font-weight:700;margin:1.2mm 0;">'
            . 'Caution&nbsp;:&nbsp;oui&nbsp;' . $cautionOui
            . '&nbsp;&nbsp;&nbsp;non&nbsp;' . $cautionNon . '</div>';

        $R .= $this->sep();

        // ── ZONE G4 — Accessories ─────────────────────────────────────────────
        $acc = [
            $this->chk($delivery?->isHasExtincteur() ?? false)      . '&nbsp;Extincteur',
            $this->chk($delivery?->isHasLavage() ?? false)          . '&nbsp;Lavage',
            $this->chk($delivery?->isHasPlaqueDepannage() ?? false)  . '&nbsp;Plaque d\'empanne',
            $this->chk($delivery?->isHasCric() ?? false)            . '&nbsp;Cric',
            $this->chk($delivery?->isHasGilet() ?? false)           . '&nbsp;Gilet',
            $this->chk($delivery?->isHasRoueSecours() ?? false)     . '&nbsp;Roue secours',
            $this->chk($delivery?->isHasSiegeBebe() ?? false)       . '&nbsp;Si&egrave;ge b&eacute;b&eacute;',
        ];
        $R .= $this->accRow(array_slice($acc, 0, 3));
        $R .= $this->accRow(array_slice($acc, 3, 3));
        $R .= $this->accRow(array_slice($acc, 6));
        $R .= $this->fld('NB', $this->h($delivery?->getEquipementNotes() ?? ''));

        $R .= $this->sep();

        // ── ZONE G5 — Fuel gauge (5-segment table, no SVG) ───────────────────
        $R .= $this->fuelTable($fuelOut);

        $R .= $this->sep();

        // ── ZONE G6 — Car diagram (HTML table, no SVG) ───────────────────────
        $R .= '<div style="font-size:5.5pt;font-weight:900;text-align:center;'
            . 'border:1.5px solid #000;padding:0.8mm 0;margin-bottom:1.2mm;'
            . 'letter-spacing:0.3px;">ETAT DU V&Eacute;HICULE AVANT LA LOCATION</div>';
        $R .= $this->carDiagram();

        // ── ZONE G7 — Society signature (right column) ────────────────────────
        $R .= '<div style="font-size:5.5pt;font-weight:900;text-align:center;'
            . 'text-decoration:underline;margin:2mm 0 1mm;">'
            . 'Signature de La Soci&eacute;t&eacute;</div>';
        $R .= $this->fld('Fait &agrave;', $this->h($contrat->getFaitA() ?? ''));
        $R .= $this->fld('Le',            $this->fmtDate($contrat->getSignedAt()));
        $R .= '<table width="100%" cellspacing="0" cellpadding="0" style="margin-top:5mm;">'
            . '<tr>'
            . '<td style="text-align:center;font-size:5.5pt;border-top:1px solid #000;'
            . 'padding-top:0.5mm;">&agrave; la livraison</td>'
            . '<td style="text-align:center;font-size:5.5pt;border-top:1px solid #000;'
            . 'padding-top:0.5mm;">&agrave; la reprise</td>'
            . '</tr></table>';

        // ── Body table (F + G columns) ────────────────────────────────────────
        $body = '<table width="100%" cellspacing="0" cellpadding="0"'
              . ' style="border-left:2px solid #000;border-right:2px solid #000;'
              . 'border-bottom:2px solid #000;border-collapse:collapse;">'
              . '<tr>'
              . '<td width="50%" style="vertical-align:top;padding:1.5mm 3mm;'
              . 'border-right:2px solid #000;">' . $L . '</td>'
              . '<td width="50%" style="vertical-align:top;padding:1.5mm 3mm;">'
              . $R . '</td>'
              . '</tr></table>';

        // ── ZONES H1–H4 — Signature section ──────────────────────────────────
        $arSigDecl = $this->arText(
            'عند التوقيع المستأجر يقر بقراءة الشروط والأحكام المنصوص عليها على ظهر هذا العقد.'
        );

        $sigSection = '<table width="100%" cellspacing="0" cellpadding="0"'
                    . ' style="border-left:2px solid #000;border-right:2px solid #000;'
                    . 'border-bottom:2px solid #000;border-collapse:collapse;">'
                    . '<tr>'

                    // H1+H2+H3 — Locataire side
                    . '<td width="50%" style="padding:1.5mm 3mm;'
                    . 'border-right:2px solid #000;vertical-align:top;">'
                    . '<div style="font-size:5.8pt;line-height:1.4;text-align:right;margin-bottom:1mm;">'
                    . $arSigDecl . '</div>'
                    . '<div style="font-size:5.5pt;line-height:1.3;margin-bottom:2mm;">'
                    . '&Agrave; la signature le locataire d&eacute;clare avoir pris connaissance '
                    . 'des clauses et conditions stipul&eacute;es au verso du pr&eacute;sent contrat.'
                    . '</div>'
                    . '<div style="font-size:6pt;font-weight:bold;text-align:center;'
                    . 'margin-bottom:2mm;">Signature du Locataire</div>'
                    . '<table width="100%" cellspacing="0" cellpadding="0"><tr>'
                    . '<td width="50%" style="font-size:5.5pt;">&#9679;&nbsp;1er conducteur</td>'
                    . '<td width="50%" style="font-size:5.5pt;">&#9679;&nbsp;2&egrave;me conducteur</td>'
                    . '</tr></table>'
                    . '</td>'

                    // H4 — Société side
                    . '<td width="50%" style="padding:1.5mm 3mm;vertical-align:top;">'
                    . '<div style="font-size:6pt;font-weight:bold;text-align:center;'
                    . 'text-decoration:underline;margin-bottom:1mm;">'
                    . 'Signature de La Soci&eacute;t&eacute;</div>'
                    . $this->fld('Fait &agrave;', $this->h($contrat->getFaitA() ?? ''))
                    . $this->fld('Le', $this->fmtDate($contrat->getSignedAt()))
                    . '<div style="height:10mm;"></div>'
                    . '<table width="100%" cellspacing="0" cellpadding="0"><tr>'
                    . '<td width="50%" style="text-align:center;font-size:5.5pt;'
                    . 'border-top:1px solid #000;border-right:1px solid #000;'
                    . 'padding-top:0.5mm;">&agrave; la livraison</td>'
                    . '<td width="50%" style="text-align:center;font-size:5.5pt;'
                    . 'border-top:1px solid #000;padding-top:0.5mm;">&agrave; la reprise</td>'
                    . '</tr></table>'
                    . '</td>'

                    . '</tr></table>';

        // ── ZONE I — Legal footer ─────────────────────────────────────────────
        $arFoot = $this->arText(
            'كل ضرر يمس الشركة كراء من طرف المستأجر سيعرضه للمساءلة الإدارية والقانونية إلى حين جبر الضرر مع أداء جميع المصاريف الناتجة عن ذلك.'
        );
        $footer = '<div style="border:1px solid #000;padding:1.5mm 4mm;'
                . 'font-size:5.5pt;line-height:1.4;">'
                . '<div style="font-style:italic;margin-bottom:0.8mm;text-align:right;">'
                . $arFoot . '</div>'
                . '<div style="font-style:italic;">'
                . 'Chaque dommage touche la soci&eacute;t&eacute; pendant la p&eacute;riode de location, '
                . 'le locataire sera expos&eacute; &agrave; la responsabilit&eacute; administrative '
                . 'et judiciaire jusqu\'a decision finale, ainsi le paiement de tous les frais '
                . 'r&eacute;sultants.'
                . '</div></div>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
             . '<style>' . $this->getCss() . '</style></head><body>'
             . $hdr . $phoneBar . $addrBar . $body . $sigSection . $footer
             . '<div style="page-break-after:always;"></div>'
             . $this->buildPage2()
             . '</body></html>';
    }

    // ── Page 2 — CONDITIONS GENERALES ─────────────────────────────────────────

    private function buildPage2(): string
    {
        $arPageTitle = $this->arText('الشروط العامة');

        $hdr = '<table width="100%" cellspacing="0" cellpadding="0"'
             . ' style="border:2px solid #000;border-collapse:collapse;">'
             . '<tr>'
             . '<td width="50%" style="text-align:center;font-size:10pt;font-weight:900;'
             . 'letter-spacing:1.5px;padding:2.5mm;border-right:2px solid #000;">'
             . 'CONDITIONS GENERALES</td>'
             . '<td width="50%" style="text-align:center;font-size:10pt;font-weight:900;'
             . 'padding:2.5mm;">' . $arPageTitle . '</td>'
             . '</tr></table>';

        $arFoot2 = $this->arText(
            'كل ضرر يمس الشركة كراء من طرف المستأجر سيعرضه للمساءلة الإدارية والقانونية إلى حين جبر الضرر مع أداء جميع المصاريف الناتجة عن ذلك.'
        );

        $body = '<table width="100%" cellspacing="0" cellpadding="0"'
              . ' style="border:2px solid #000;border-top:0;border-collapse:collapse;">'
              . '<tr>'
              . '<td width="50%" style="vertical-align:top;padding:2mm 3mm;'
              . 'border-right:2px solid #000;font-size:5.5pt;line-height:1.32;">'
              . $this->frArticles()
              . '</td>'
              . '<td width="50%" style="vertical-align:top;padding:2mm 3mm;'
              . 'font-size:5.5pt;line-height:1.32;text-align:right;">'
              . $this->arArticles()
              . '</td>'
              . '</tr></table>'
              . '<div style="border:2px solid #000;border-top:0;padding:1.5mm 4mm;'
              . 'font-size:5.5pt;line-height:1.4;">'
              . '<div style="font-style:italic;font-weight:700;margin-bottom:0.7mm;text-align:right;">'
              . $arFoot2 . '</div>'
              . '<div style="font-style:italic;font-weight:700;">'
              . 'Chaque dommage touche la soci&eacute;t&eacute; pendant la p&eacute;riode de location, '
              . 'le locataire sera expos&eacute; &agrave; la responsabilit&eacute; administrative et judiciaire '
              . 'jusqu\'a decision finale, ainsi le paiement de tous les frais r&eacute;sultants.'
              . '</div></div>';

        return $hdr . $body;
    }

    // ── Article helpers ───────────────────────────────────────────────────────

    private function at(string $title): string
    {
        return '<div style="font-weight:900;font-size:6pt;margin:2mm 0 0.5mm;">' . $title . '</div>';
    }

    private function ab(string $html): string
    {
        return '<div style="font-size:5.5pt;line-height:1.32;margin-bottom:0.5mm;text-align:justify;">'
             . $html . '</div>';
    }

    private function atAr(string $title): string
    {
        return '<div style="font-weight:900;font-size:6pt;margin:2mm 0 0.5mm;text-align:right;">'
             . $this->arText($title) . '</div>';
    }

    private function abAr(string $html): string
    {
        return '<div style="font-size:5.5pt;line-height:1.32;margin-bottom:0.5mm;text-align:right;">'
             . $this->arHtml($html) . '</div>';
    }

    // ── French articles (1–9) ─────────────────────────────────────────────────

    private function frArticles(): string
    {
        $o = '';

        $o .= $this->at('ARTICLE 1 : UTILISATION DE VOITURE');
        $o .= $this->ab(
            '-Le locataire s\'engage &agrave; ne pas laisser conduire la voiture que par ceux d&eacute;sign&eacute;s au contrat.<br>'
            . '-Ne pas utiliser le v&eacute;hicule &agrave; des fins illicites ou pour le transport de marchandises interdites '
            . 'le remorquage ou le transport des personnes &agrave; contre partie.<br>'
            . '-Ne pas utiliser le v&eacute;hicule dans les pistes.'
        );

        $o .= $this->at('ARTICLE 2 : &Eacute;TAT DE VOITURE');
        $o .= $this->ab(
            '-Le v&eacute;hicule est livr&eacute; en parfait &eacute;tat de propret&eacute;, m&eacute;canique, &eacute;lectrique '
            . 'et pneumatique&nbsp;; doit &ecirc;tre rendu dans les m&ecirc;mes conditions.<br>'
            . '-Si le v&eacute;hicule est lou&eacute; moins de 03 jours le kilom&eacute;trage est fix&eacute; &agrave; 250 Km/jour. '
            . 'En cas de d&eacute;passement, le locataire paiera 1.20 DH pour chaque kilom&egrave;tre de plus. '
            . 'Au-del&agrave; de 03 jours le kilom&eacute;trage est illimit&eacute;.'
        );

        $o .= $this->at('ARTICLE 3 : ENTRETIEN ET R&Eacute;PARATION');
        $o .= $this->ab(
            'Toute op&eacute;ration d\'entretien ou de r&eacute;paration doit &ecirc;tre accord&eacute;e par l\'agence par Email ou Fax.<br>'
            . '-Le locataire doit v&eacute;rifier les niveaux (huile, eau, lumi&egrave;res) si la dur&eacute;e d&eacute;passe <b>un jour.</b><br>'
            . '-Toute panne caus&eacute;e par l\'inconscience du client sera &agrave; sa charge.<br>'
            . '-<i>L\'agence n\'est pas responsable des violations li&eacute;es aux feux de la voiture.</i>'
        );

        $o .= $this->at('ARTICLE 4 : ASSURANCES');
        $o .= $this->ab(
            '-<i>Assurance RC uniquement. Le locataire assume l\'enti&egrave;re responsabilit&eacute; '
            . 'de r&eacute;paration en cas d\'accident et frais d\'immobilisation.</i><br>'
            . '-Le locataire doit aviser imm&eacute;diatement l\'agence <b>en cas d\'accident.</b>'
        );

        $o .= $this->at('ARTICLE 5 : LOCATION &amp; PROLONGATION');
        $o .= $this->ab(
            '-Le paiement est payable &agrave; l\'avance. <b><i>Faute de cela le locataire est responsable de tous les frais.</i></b><br>'
            . '-En cas de prolongation le locataire doit aviser 2 jours &agrave; l\'avance.<br>'
            . '-Pour tout retard de retour (plus de 2 heures), une journ&eacute;e sera factur&eacute;e.<br>'
            . '-La soci&eacute;t&eacute; peut mettre fin au contrat sans justification ni compensation.<br>'
            . '-En cas de retour avant terme, aucun remboursement ne sera effectu&eacute;.<br>'
            . '-Prolongation non autoris&eacute;e&nbsp;: forfait 100 DH/heure de retard jusqu\'&agrave; reprise.'
        );

        $o .= $this->at('ARTICLE 6 : PAPIERS DE LA VOITURE');
        $o .= $this->ab('-En cas de perte de papiers, tous les frais sont &agrave; la charge du locataire.');

        $o .= $this->at('ARTICLE 7 : RESPONSABILIT&Eacute;');
        $o .= $this->ab(
            '-Le locataire est responsable des amendes et contraventions &eacute;tablies par les autorit&eacute;s.<br>'
            . '-Le conducteur est financ&egrave;rement responsable en cas de conduite sous l\'emprise de l\'alcool ou drogues.<br>'
            . '-<i>Paiement anticip&eacute; non remboursable en cas de Restart, Annulation ou Changement.</i><br>'
            . '-<b>Le client assume la responsabilit&eacute; de tout d&eacute;g&acirc;t en cas d\'accident sans permis valide.</b><br>'
            . '-<b>D&eacute;passement de 50 km/h de la vitesse l&eacute;gale&nbsp;: reprise imm&eacute;diate sans compensation.</b>'
        );

        $o .= $this->at('ARTICLE 8 : ANNULATION DU DROIT DE LOCATAIRE');
        $o .= $this->ab(
            '-Tous les droits du locataire sont exclus en cas de non-respect du contrat, '
            . 'sans pr&eacute;tendre &agrave; aucune indemnit&eacute;.'
        );

        $o .= $this->at('ARTICLE 9 : LITIGES');
        $o .= $this->ab(
            '-Toutes contestations rel&egrave;vent de la comp&eacute;tence exclusive du si&egrave;ge social de la soci&eacute;t&eacute;.'
        );

        return $o;
    }

    // ── Arabic articles (1–9, reshaped via ArPHP) ─────────────────────────────

    private function arArticles(): string
    {
        $o = '';

        $o .= $this->atAr('البند 1 : استعمال السيارة');
        $o .= $this->abAr(
            'لا يسمح بسياقة السيارة الا من طرف الأشخاص المحددة أسماؤهم في العقد.<br>'
            . 'لا يسمح باستخدام السيارة لأغراض غير مشروعة، (نقل البضائع الممنوعة جر العربيات، أو نقل الأشخاص بمقابل).<br>'
            . 'يمنع استعمال السيارة في الطرق غير المعبدة'
        );

        $o .= $this->atAr('البند 2 : حالة السيارة');
        $o .= $this->abAr(
            'تسلم السيارة في حالة جيدة(النظافة، الحالة الميكانيكية، الاضواء، العجلات) على ان تعاد في نفس الظروف<br>'
            . 'اذا استأجرت السيارة أقل من 03 أيام يحدد عدد الكيلومترات في 250 كلم/يوم وفي حالة تجاوز العدد المحدد يحتسب 1.20 درهم للكيلومتر الواحد<br>'
            . 'اما اذا تجاوزت المدة 03 أيام ما يبقى عدد الكيلومترات <b>في 350 كلم/يوم</b>'
        );

        $o .= $this->atAr('البند 3 : الصيانة و الاصلاح');
        $o .= $this->abAr(
            'لايمكن القيام بأي عملية صيانة أو إصلاح بالسيارة، إلا بعد الحصول على موافقة الوكالة عن طريق الفاكس أو البريد الالكتروني.<br>'
            . 'يجب على المستأجر التحقق من مستوياته (زيت المحرك،الماء، أضواء السيارات.) إذا تجاوزت المدة 24 ساعة<br>'
            . 'أي عطل ناجم عن إهمال من طرف المستأجر، يلزمه أداء كل النفقات المترتبة عن ذلك.'
        );

        $o .= $this->atAr('البند 4 : التأمين');
        $o .= $this->abAr(
            '<b>-التأمين على المسؤولية المدنية فقط إذ أن المكتري يتحمل كل المسؤولية المتعلقة بإصلاح السيارة</b><br>'
            . '<b>في حالة وقوع حادثة، بالإضافة إلى المصاريف الناتجة عن توقف السيارة خلال فترة الإصلاح</b><br>'
            . 'يجب على المستأجر إخبار وكالة الكراء فور حدوث حادثة سير.'
        );

        $o .= $this->atAr('البند 5 : التمديد');
        $o .= $this->abAr(
            '-يودى مسبقا المبلغ الاجمالي للإيجار.<br>'
            . '-لا يمكن تمديد عملية الإيجار الا بموافقة الوكالة ، والا سيتحمل المستأجر كل المصاريف الناتجة عن هذا الإهمال.<br>'
            . 'يجب على المستأجر إرجاع السيارة في الوقت المحدد . وأي تأخر (في حدود ساعتين) يحتسب يوما إضافيا يوديه المستأجر.<br>'
            . 'في حالة إرجاع سابق لأوانه لا يمكن للمكتري أن يطالب بأي تسديد أو تخفيض<br>'
            . 'في حالة انتهاء مدة العقد وعدم تمديده من طرف الشركة يبقى المكتري ملتزما بتأدية غرامة جزائية مقدارها 100 درهم عن كل ساعة تأخر إلى حين استرجاع السيارة.'
        );

        $o .= $this->atAr('البند 6 : أوراق السيارة');
        $o .= $this->abAr(
            'في حالة فقدان وثائق السيارة ، يبقى المستأجر هو المسؤول عن تكاليف إنجاز الأوراق و عن أيام توقف السيارة.'
        );

        $o .= $this->atAr('البند 7 : المسؤولية');
        $o .= $this->abAr(
            '- يبقى المستأجر المسؤول الوحيد عن الغرامات، المخالفات وكل المحاضر المحررة ضده من قبل السلطات المعنية.<br>'
            . '- يلبقى السائق مسؤولا ماليا عن الاضرار التي لحقت بالسيارة خلال مدة الكراء عند القيادة تحت تأثير الكحول أو المخدرات أو الادوية المحظورة أثناء السياقة<br>'
            . '-<b>يمكن استرجاع أو المطالبة بالمبلغ المسبق للحجز في الحالات التالية: الإلغاء - تغيير نوع السيارة</b><br>'
            . '-يحق للشركة استرجاع السيارة المكترأة إذا ما ثبت أو أفيت في حق المكتري قانونيًا بمخالفة أو محضر رسمي<br>'
            . 'يتحمل الزبون كل الخسائر في حالة وقوع حادثة وعدم توفره على رخصة السياقة لسببها منه لمخالفة أوغيرها<br>'
            . '-تجاوز السرعة المحددة قانونيًا بـ 50Km/h يخول للشركة استرجاع السيارة بدون تبرير ولا تعويض'
        );

        $o .= $this->atAr('البند 8 : سقوط حق المكتري');
        $o .= $this->abAr(
            'تسقط كافة حقوق المكتري الممنوحة له بمقتضى هذا العقد في حالة عدم احترامه لأي من هذا العقد من عدم المطالبة بعدم التعويض عن المدة المتبقية.'
        );

        $o .= $this->atAr('البند 9 : المنازعات');
        $o .= $this->abAr(
            'جميع المنازعات التي قد تنشأ بين شركة التأجير و المستأجر تبقى في الاختصاص الحصري للمحاكم التابعة لمقر الشركة.'
        );

        return $o;
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private function getCss(): string
    {
        return '
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 6.5pt;
    color: #000;
    margin: 5mm 6mm 5mm 6mm;
}
b  { font-weight: 900; }
i  { font-style: italic; }
';
    }
}
