#!/usr/bin/env python3
# Baut aus dem Kontextdossier (eingeschraenktes Markdown) eine .docx ohne externe Pakete.
import re, sys, zipfile

SRC = sys.argv[1]
OUT = sys.argv[2]

def esc(s):
    return s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')

MONO = 'Consolas'
BODY = 'Calibri'

def runs(text):
    """Inline: **fett** und `code` -> w:r-Folge."""
    out = []
    for teil in re.split(r'(\*\*[^*]+\*\*|`[^`]+`)', text):
        if not teil:
            continue
        if teil.startswith('**') and teil.endswith('**') and len(teil) > 4:
            out.append('<w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">%s</w:t></w:r>'
                       % esc(teil[2:-2]))
        elif teil.startswith('`') and teil.endswith('`') and len(teil) > 2:
            out.append('<w:r><w:rPr><w:rFonts w:ascii="%s" w:hAnsi="%s"/><w:sz w:val="19"/>'
                       '<w:color w:val="1F3864"/></w:rPr><w:t xml:space="preserve">%s</w:t></w:r>'
                       % (MONO, MONO, esc(teil[1:-1])))
        else:
            out.append('<w:r><w:t xml:space="preserve">%s</w:t></w:r>' % esc(teil))
    return ''.join(out) or '<w:r><w:t/></w:r>'

def para(text, size=21, bold=False, mono=False, color=None, before=0, after=120, ind=0):
    rpr = ''
    if mono:
        rpr += '<w:rFonts w:ascii="%s" w:hAnsi="%s"/>' % (MONO, MONO)
    if bold:
        rpr += '<w:b/>'
    if color:
        rpr += '<w:color w:val="%s"/>' % color
    rpr += '<w:sz w:val="%d"/>' % size
    ppr = '<w:spacing w:before="%d" w:after="%d"/>' % (before, after)
    if ind:
        ppr += '<w:ind w:left="%d"/>' % ind
    if mono:
        ppr += ('<w:shd w:val="clear" w:fill="F2F4F8"/>'
                '<w:spacing w:before="%d" w:after="%d" w:line="240" w:lineRule="auto"/>'
                % (before, after))
    body = ('<w:r><w:rPr>%s</w:rPr><w:t xml:space="preserve">%s</w:t></w:r>' % (rpr, esc(text))
            if (mono or bold or color) else runs(text))
    if (mono or bold or color) and not mono:
        body = '<w:r><w:rPr>%s</w:rPr><w:t xml:space="preserve">%s</w:t></w:r>' % (rpr, esc(text))
    return '<w:p><w:pPr>%s<w:rPr>%s</w:rPr></w:pPr>%s</w:p>' % (ppr, rpr, body)

def heading(text, level):
    size = {1: 40, 2: 30, 3: 24}[level]
    return ('<w:p><w:pPr><w:spacing w:before="%d" w:after="120"/><w:keepNext/>'
            '<w:rPr><w:b/><w:color w:val="1F3864"/><w:sz w:val="%d"/></w:rPr></w:pPr>'
            '<w:r><w:rPr><w:b/><w:color w:val="1F3864"/><w:sz w:val="%d"/></w:rPr>'
            '<w:t xml:space="preserve">%s</w:t></w:r></w:p>'
            % (360 if level > 1 else 0, size, size, esc(text)))

BORD = ''.join('<w:%s w:val="single" w:sz="4" w:space="0" w:color="C6CFE0"/>' % s
               for s in ('top', 'left', 'bottom', 'right', 'insideH', 'insideV'))

def table(rows):
    breite = max(len(r) for r in rows)
    grid = ''.join('<w:gridCol w:w="%d"/>' % (9360 // breite) for _ in range(breite))
    xml = ['<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>%s</w:tblBorders>'
           '<w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>%s</w:tblGrid>' % (BORD, grid)]
    for i, r in enumerate(rows):
        kopf = (i == 0)
        zellen = []
        for j in range(breite):
            txt = r[j] if j < len(r) else ''
            shd = '<w:shd w:val="clear" w:fill="EEF2F9"/>' if kopf else ''
            inhalt = ('<w:p><w:pPr><w:spacing w:before="40" w:after="40"/>'
                      '<w:rPr><w:b/><w:sz w:val="19"/></w:rPr></w:pPr>'
                      '<w:r><w:rPr><w:b/><w:sz w:val="19"/></w:rPr>'
                      '<w:t xml:space="preserve">%s</w:t></w:r></w:p>' % esc(txt)) if kopf else \
                     ('<w:p><w:pPr><w:spacing w:before="40" w:after="40"/>'
                      '<w:rPr><w:sz w:val="19"/></w:rPr></w:pPr>%s</w:p>'
                      % runs(txt).replace('<w:rPr>', '<w:rPr><w:sz w:val="19"/>')
                        .replace('<w:r><w:t', '<w:r><w:rPr><w:sz w:val="19"/></w:rPr><w:t'))
            zellen.append('<w:tc><w:tcPr><w:tcW w:w="%d" w:type="dxa"/>%s</w:tcPr>%s</w:tc>'
                          % (9360 // breite, shd, inhalt))
        trpr = '<w:trPr><w:tblHeader/></w:trPr>' if kopf else ''
        xml.append('<w:tr>%s%s</w:tr>' % (trpr, ''.join(zellen)))
    xml.append('</w:tbl><w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>')
    return ''.join(xml)

# ---------- Parser ----------
zeilen = open(SRC, encoding='utf-8').read().split('\n')
teile, i, absatz = [], 0, []

def absatz_leeren():
    global absatz
    if absatz:
        teile.append(para(' '.join(absatz)))
        absatz = []

while i < len(zeilen):
    z = zeilen[i]
    s = z.strip()
    if s.startswith('```'):
        absatz_leeren()
        i += 1
        code = []
        while i < len(zeilen) and not zeilen[i].strip().startswith('```'):
            code.append(zeilen[i])
            i += 1
        i += 1
        for k, c in enumerate(code):
            teile.append(para(c or ' ', size=18, mono=True,
                              before=60 if k == 0 else 0,
                              after=60 if k == len(code) - 1 else 0))
        continue
    if s.startswith('#'):
        absatz_leeren()
        lvl = len(s) - len(s.lstrip('#'))
        teile.append(heading(s.lstrip('#').strip(), min(lvl, 3)))
        i += 1
        continue
    if s.startswith('|') and s.endswith('|'):
        absatz_leeren()
        rows = []
        while i < len(zeilen) and zeilen[i].strip().startswith('|'):
            r = [c.strip() for c in zeilen[i].strip().strip('|').split('|')]
            if not all(re.fullmatch(r':?-{2,}:?', c) for c in r if c):
                rows.append(r)
            i += 1
        teile.append(table(rows))
        continue
    if s.startswith('- '):
        absatz_leeren()
        punkt = [s[2:]]
        i += 1
        while i < len(zeilen) and zeilen[i].startswith('  ') and zeilen[i].strip():
            punkt.append(zeilen[i].strip())
            i += 1
        teile.append(para('• ' + ' '.join(punkt), ind=283))
        continue
    if s == '---' or s == '':
        absatz_leeren()
        i += 1
        continue
    absatz.append(s)
    i += 1
absatz_leeren()

DOC = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
       '<w:body>%s<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
       '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/>'
       '</w:sectPr></w:body></w:document>' % ''.join(teile))

CT = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      '<Default Extension="xml" ContentType="application/xml"/>'
      '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
      '</Types>')

RELS = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        '</Relationships>')

DRELS = ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>')

with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr('[Content_Types].xml', CT)
    z.writestr('_rels/.rels', RELS)
    z.writestr('word/_rels/document.xml.rels', DRELS)
    z.writestr('word/document.xml', DOC)

print('geschrieben:', OUT, len(DOC), 'Zeichen document.xml,', len(teile), 'Blöcke')
