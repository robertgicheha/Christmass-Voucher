"""Builds invitation.html: an A5 two-page vector recreation of the Moureen & Charles wedding invitation.

All text is live text; all artwork is inline SVG, so the PDF stays sharp at any zoom and the
PNG/JPG exports can be rendered at any resolution. Run render.py afterwards for the exports.
"""
import io
import os

HERE = os.path.dirname(os.path.abspath(__file__))
import base64
PAPER = 'data:image/jpeg;base64,' + base64.b64encode(open(os.path.join(HERE, 'paper.jpg'), 'rb').read()).decode()

INK = '#3b2d6e'        # deep purple-navy used for the printed text
VERSE = '#34305a'
BAND = '#6a4fa8'       # purple wave band
LINE_BLUE = '#5b9fcf'

# ---------------------------------------------------------------- artwork

def rose_symbol(sid, light, mid, dark, line):
    """A rose seen from above: outer petal ring, gradient cup, inner petals, spiral centre. viewBox -50 -50 100 100."""
    petal = 'M0,-2 C-30,-8 -34,-46 0,-49 C34,-46 30,-8 0,-2 Z'
    outer = ''.join(f'<path transform="rotate({a})" d="{petal}"/>' for a in range(0, 360, 45))
    middle = ''.join(f'<path transform="rotate({a}) scale(.74)" d="{petal}"/>' for a in range(22, 382, 60))
    inner = ''.join(f'<path transform="rotate({a}) scale(.5)" d="{petal}"/>' for a in range(10, 370, 72))
    return f'''
  <radialGradient id="{sid}-g" cx="50%" cy="45%" r="55%">
    <stop offset="0" stop-color="{light}"/><stop offset=".6" stop-color="{mid}"/><stop offset="1" stop-color="{dark}"/>
  </radialGradient>
  <radialGradient id="{sid}-c" cx="50%" cy="50%" r="50%">
    <stop offset="0" stop-color="{dark}"/><stop offset=".7" stop-color="{mid}"/><stop offset="1" stop-color="{light}"/>
  </radialGradient>
  <symbol id="{sid}" viewBox="-50 -50 100 100" overflow="visible">
    <g stroke="{line}" stroke-width="1" stroke-linejoin="round">
      <g fill="{dark}">{outer}</g>
      <g fill="url(#{sid}-g)">{middle}</g>
      <g fill="{mid}">{inner}</g>
      <circle r="15" fill="url(#{sid}-c)" stroke="none"/>
      <path fill="none" stroke-width="1.5" stroke-linecap="round" d="M0,-3 C5,-4 6,2 2,4 C-3,6 -7,1 -5,-4 C-2,-9 7,-9 10,-3 C13,4 7,11 -1,11 C-9,11 -13,3 -10,-6"/>
      
    </g>
  </symbol>'''


def leaf_symbol(sid, light, dark, line):
    return f'''
  <linearGradient id="{sid}-g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="{light}"/><stop offset="1" stop-color="{dark}"/>
  </linearGradient>
  <symbol id="{sid}" viewBox="0 -14 44 28" overflow="visible">
    <path fill="url(#{sid}-g)" stroke="{line}" stroke-width=".8" d="M0,0 C8,-12 28,-14 44,0 C28,13 8,12 0,0 Z"/>
    <path fill="none" stroke="{line}" stroke-width=".8" d="M2,0 C16,-1 30,-1 42,0 M14,0 L20,-6 M24,0 L30,-6 M14,0 L20,6 M24,0 L30,6"/>
  </symbol>'''


def leaf(sym, x, y, rot, scale=1.0):
    return f'<use href="#{sym}" x="0" y="0" width="44" height="28" transform="translate({x} {y}) rotate({rot}) scale({scale}) translate(0 -14)"/>'


def rose(sym, x, y, r):
    return f'<use href="#{sym}" x="{x - r}" y="{y - r}" width="{2 * r}" height="{2 * r}"/>'


DOVE = '''
  <linearGradient id="wingShade" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#d9d6e6"/>
  </linearGradient>
  <symbol id="dove" viewBox="0 -30 120 122" overflow="visible">
    <g fill="#fff" stroke="#6f6a88" stroke-width=".9" stroke-linejoin="round" stroke-linecap="round">
      <!-- far wing, raised behind the body -->
      <path fill="url(#wingShade)" d="M62,52 C52,36 50,8 58,-26 C62,-8 68,4 74,12 C73,-2 75,-14 80,-24 C84,-6 86,10 85,22 C88,12 92,4 96,-2 C98,18 92,38 80,50 Z"/><path fill="none" d="M62,40 C60,22 60,6 62,-10 M70,42 C69,28 70,16 73,6 M77,44 C78,32 80,22 84,14"/>
      <!-- tail fan -->
      <path d="M32,64 C22,68 11,74 2,84 C9,86 15,85 21,82 C17,86 16,90 18,92 C25,88 31,81 36,75 C35,80 36,84 38,86 C41,79 42,73 42,68 Z"/>
      <path fill="none" d="M10,82 L30,70 M18,88 L34,73 M26,86 L38,74"/>
      <!-- body and head -->
      <path d="M32,64 C42,53 57,49 71,51 C77,43 85,39 93,41 C98,42 101,45 103,48 L110,50 L102,52.5 C100,56 96,59 91,61 C85,71 71,77 57,77 C46,77 38,72 32,64 Z"/>
      <!-- near wing, swept back over the body -->
      <path fill="url(#wingShade)" d="M60,60 C46,50 32,34 24,6 C36,16 46,22 56,26 C50,14 49,4 51,-6 C58,10 64,22 68,32 C68,24 69,16 72,10 C76,26 77,42 76,56 Z"/>
      <path fill="none" d="M41,37 C48,44 56,50 64,56 M47,30 C54,40 62,48 69,55 M58,22 C63,34 68,44 73,54"/>
      <path fill="none" d="M66,23 C70,33 73,40 76,47 M72,14 C75,26 77,36 78,44"/>
      <circle cx="95.5" cy="46" r="1.3" fill="#3b3550" stroke="none"/>
      <path fill="none" d="M84,66 C78,70 72,72 66,72"/>
    </g>
  </symbol>'''

OLIVE = '''
  <symbol id="olive" viewBox="0 0 40 16" overflow="visible">
    <g fill="#8f8aa8" stroke="#6f6a88" stroke-width=".5">
      <path fill="none" stroke-width=".9" d="M0,8 C10,10 22,10 40,6"/>
      <ellipse cx="8" cy="4.5" rx="4" ry="1.5" transform="rotate(-25 8 4.5)"/>
      <ellipse cx="14" cy="12.5" rx="4" ry="1.5" transform="rotate(25 14 12.5)"/>
      <ellipse cx="21" cy="5" rx="4" ry="1.5" transform="rotate(-20 21 5)"/>
      <ellipse cx="28" cy="11" rx="4" ry="1.5" transform="rotate(22 28 11)"/>
      <ellipse cx="33" cy="3.5" rx="3.6" ry="1.4" transform="rotate(-25 33 3.5)"/>
    </g>
  </symbol>'''

FLOURISH = '''
  <symbol id="flourish" viewBox="0 0 240 44" overflow="visible">
    <g id="flourishHalf" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <path fill="currentColor" stroke="none" d="M120,34 C110,25 96,24 86,30 C94,36 110,38 120,34 Z"/>
      <path d="M88,31 C70,38 52,22 34,26 C20,29 14,38 4,33 C-2,30 2,22 8,24 C13,26 11,32 6,30"/>
      <path d="M78,32 C70,22 56,24 58,32 C60,38 70,36 68,30"/>
      <path stroke-width="1.4" d="M40,26 C46,18 56,16 62,20"/>
    </g>
    <use href="#flourishHalf" transform="translate(240 0) scale(-1 1)"/>
    <g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
      <path d="M120,30 L120,10 M120,30 L113,13 M120,30 L127,13 M120,30 L107,18 M120,30 L133,18 M120,30 L103,24 M120,30 L137,24"/>
      <path d="M120,34 L120,42"/>
    </g>
    <circle cx="120" cy="8" r="2" fill="currentColor"/>
  </symbol>'''

GOLD_RINGS = '''
  <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#fff3b0"/><stop offset=".3" stop-color="#e2b23c"/>
    <stop offset=".6" stop-color="#a8761a"/><stop offset=".85" stop-color="#f1cd62"/><stop offset="1" stop-color="#8a5d10"/>
  </linearGradient>
  <linearGradient id="gold2" x1="1" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#fff3b0"/><stop offset=".35" stop-color="#d9a634"/>
    <stop offset=".7" stop-color="#9a6a15"/><stop offset="1" stop-color="#f0c95a"/>
  </linearGradient>
  <symbol id="goldRings" viewBox="0 0 100 70" overflow="visible">
    <ellipse cx="38" cy="42" rx="25" ry="20" fill="none" stroke="url(#gold)" stroke-width="7"/>
    <ellipse cx="38" cy="42" rx="25" ry="20" fill="none" stroke="#fff6c8" stroke-width="1" stroke-opacity=".7" transform="translate(-1.5 -1.5)"/>
    <ellipse cx="62" cy="32" rx="24" ry="20" fill="none" stroke="url(#gold2)" stroke-width="7" transform="rotate(-14 62 32)"/>
    <path d="M14,46 C16,58 30,64 44,61" fill="none" stroke="url(#gold)" stroke-width="7"/>
    <ellipse cx="62" cy="32" rx="24" ry="20" fill="none" stroke="#fff6c8" stroke-width="1" stroke-opacity=".6" transform="rotate(-14 62 32) translate(-1.5 -1.5)"/>
  </symbol>'''

SILVER_RINGS = '''
  <linearGradient id="silver" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#f4f2fa"/><stop offset=".4" stop-color="#8c87a6"/>
    <stop offset=".7" stop-color="#4a4566"/><stop offset="1" stop-color="#b9b4cf"/>
  </linearGradient>
  <symbol id="ringArt" viewBox="0 0 140 120" overflow="visible">
    <!-- floral swirls rising behind the rings -->
    <g fill="none" stroke="#5d5680" stroke-width="1.3" stroke-linecap="round">
      <path d="M66,72 C60,56 64,40 76,30 C86,22 88,10 80,6 C74,4 70,10 74,14"/>
      <path d="M70,70 C74,56 86,48 98,48 C108,48 112,40 106,34 C102,30 96,34 100,38"/>
      <path d="M62,70 C52,62 40,62 34,54 C30,48 34,42 40,44"/>
      <path d="M76,66 C90,66 100,74 104,86 C106,94 114,96 118,90"/>
      <path d="M58,74 C46,80 40,92 44,102 C46,108 54,108 54,102"/>
      <path d="M72,40 C64,36 58,26 62,18"/>
      <path d="M86,40 C92,32 102,30 108,22"/>
    </g>
    <g fill="#8d7fb6" stroke="#5d5680" stroke-width=".6">
      <circle cx="40" cy="44" r="5"/><circle cx="36" cy="40" r="3.4"/><circle cx="45" cy="40" r="3.2"/>
      <circle cx="108" cy="22" r="5.5"/><circle cx="113" cy="18" r="3.4"/><circle cx="103" cy="17" r="3.2"/>
      <circle cx="118" cy="90" r="5"/><circle cx="122" cy="95" r="3.2"/>
      <circle cx="58" cy="30" r="2.6"/><circle cx="94" cy="58" r="2.4"/><circle cx="30" cy="70" r="2.6"/>
      <circle cx="106" cy="100" r="2.6"/><circle cx="74" cy="20" r="2.2"/>
    </g>
    <g fill="#d6d1e8" stroke="#5d5680" stroke-width=".5">
      <path d="M50,58 l3,-6 l3,6 l-3,3 z"/><path d="M96,70 l3,-6 l3,6 l-3,3 z"/><path d="M84,100 l3,-6 l3,6 l-3,3 z"/>
    </g>
    <!-- two wedding bands lying flat -->
    <ellipse cx="52" cy="92" rx="30" ry="15" fill="none" stroke="url(#silver)" stroke-width="8"/>
    <ellipse cx="84" cy="94" rx="30" ry="15" fill="none" stroke="url(#silver)" stroke-width="8"/>
    <path d="M22,92 C22,102 36,108 52,107" fill="none" stroke="url(#silver)" stroke-width="8"/>
    <ellipse cx="52" cy="92" rx="30" ry="15" fill="none" stroke="#fff" stroke-width=".8" stroke-opacity=".6" transform="translate(0 -2.5)"/>
    <ellipse cx="84" cy="94" rx="30" ry="15" fill="none" stroke="#fff" stroke-width=".8" stroke-opacity=".6" transform="translate(0 -2.5)"/>
  </symbol>'''

TEXTURE = '''
  <filter id="paper" x="0" y="0" width="100%" height="100%">
    <feTurbulence type="fractalNoise" baseFrequency=".08 .11" numOctaves="4" seed="7" result="n"/>
    <feColorMatrix type="matrix" values="0 0 0 0 .55  0 0 0 0 .52  0 0 0 0 .66  0 0 0 -2.2 1.2"/>
  </filter>'''


def defs():
    return ('<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>'
            + rose_symbol('rosePink', '#fbd3d6', '#ec8f98', '#c9616e', '#9c3c4a')
            + rose_symbol('roseRed', '#f2a1a3', '#cf4d5a', '#9d2a39', '#741c28')
            + rose_symbol('roseDeep', '#e48a8f', '#b8384a', '#82192a', '#5e1320')
            + rose_symbol('roseLav', '#e6e2f2', '#b8b1d6', '#958cbf', '#7f77a8')
            + leaf_symbol('leafGreen', '#7fbf58', '#2f7a2c', '#245e22')
            + leaf_symbol('leafLav', '#cfc9e6', '#a59dc9', '#8e86b6')
            + DOVE + OLIVE + FLOURISH + GOLD_RINGS + SILVER_RINGS
            + '</defs></svg>')


def radial_leaves(sym, cx, cy, radius, angles, scale):
    import math
    out = []
    for i, ang in enumerate(angles):
        r = radius * (1 if i % 2 else .86)
        x = cx + r * math.cos(math.radians(ang)); y = cy + r * math.sin(math.radians(ang))
        out.append(leaf(sym, round(x, 1), round(y, 1), ang, scale * (1 if i % 3 else .85)))
    return ''.join(out)


def colour_bouquet():
    leaves = radial_leaves('leafGreen', 90, 82, 38, [200, 225, 250, 290, 320, 345, 10, 35, 60, 95, 130, 160, 180], 1.0)
    roses = [rose('rosePink', 70, 38, 22), rose('roseDeep', 116, 40, 19), rose('roseRed', 86, 84, 30),
             rose('rosePink', 116, 118, 26), rose('roseDeep', 56, 120, 11)]
    return ('<svg class="art" viewBox="10 -10 170 180" style="left:112mm;top:8mm;width:34mm;height:36mm">'
            + leaves + ''.join(roses) + '</svg>')


def lavender_bouquet():
    leaves = radial_leaves('leafLav', 150, 152, 62, [190, 210, 232, 255, 280, 305, 330, 350, 15, 40, 62, 90, 115, 140, 165], 1.25)
    sprigs = ('<g fill="none" stroke="#a49cc8" stroke-width="2" stroke-linecap="round">'
              '<path d="M90,120 C60,100 34,96 12,104"/><path d="M200,90 C224,64 240,52 262,46"/>'
              '<path d="M150,230 C150,256 146,276 138,296"/></g>')
    roses = [rose('roseLav', 104, 152, 46), rose('roseLav', 176, 98, 50), rose('roseLav', 170, 206, 52)]
    return ('<svg class="art lav" viewBox="0 0 300 300" style="left:8mm;top:77mm;width:59mm;height:59mm">'
            + sprigs + leaves + ''.join(roses) + '</svg>')


def doves():
    return ('<svg class="art" viewBox="0 0 400 170" style="left:68mm;top:96mm;width:80mm;height:34mm">'
            '<use href="#dove" x="8" y="6" width="170" height="173"/>'
            '<use href="#olive" x="160" y="76" width="60" height="24"/>'
            '<use href="#dove" x="0" y="0" width="170" height="173" transform="translate(394 4) scale(-1 1)"/>'
            '<use href="#olive" x="0" y="0" width="60" height="24" transform="translate(240 72) scale(-1 1)"/>'
            '</svg>')


def heart():
    return ('<svg class="art" viewBox="0 0 200 172" style="left:80mm;top:156mm;width:37mm;height:32mm">'
            f'<path fill="#5a4795" fill-rule="evenodd" d="M100,38 C80,-6 0,-2 4,60 C8,106 70,136 100,170 '
            'C130,136 192,106 196,60 C200,-2 120,-6 100,38 Z M106,46 C92,8 26,12 26,62 C28,102 80,130 104,162 '
            'C128,134 189,104 191,62 C193,10 122,6 106,46 Z"/>'
            '<use href="#goldRings" x="70" y="56" width="62" height="44"/>'
            '</svg>')


def wave():
    return (f'<svg class="art" viewBox="0 0 148 30" preserveAspectRatio="none" style="left:0;top:182mm;width:148mm;height:30mm">'
            f'<path fill="none" stroke="{LINE_BLUE}" stroke-width=".35" d="M0,10.5 C25,4.5 55,6.5 85,12.5 C110,17.5 135,14.5 148,1.5"/>'
            f'<path fill="none" stroke="{LINE_BLUE}" stroke-width=".35" d="M0,12 C25,6 55,8 85,14 C110,19 135,16 148,3"/>'
            f'<path fill="{BAND}" d="M0,14.5 C25,8.5 55,10.5 85,16.5 C110,21.5 135,18.5 148,5.5 L148,30 L0,30 Z"/>'
            '</svg>')


def zigzag(depth=2.4, step=2.6, w=148.0, h=210.0):
    pts = ['0mm 0mm', f'{w}mm 0mm', f'{w}mm {h - depth}mm']
    x, down, i = w, True, 0
    while x > 0:
        x = max(0.0, x - step / 2)
        pts.append(f'{x:.2f}mm {h if down else h - depth:.2f}mm')
        down = not down
    pts.append(f'0mm {h - depth}mm')
    return 'polygon(' + ', '.join(pts) + ')'


# ---------------------------------------------------------------- pages

FRONT = f'''
<section class="page" id="front" aria-label="Front page">

  <!-- Top panel: the couple's verses -->
  {colour_bouquet()}
  <div class="t vivaldi" style="left:17mm;top:26mm;font-size:7.4mm;color:{INK}">Moureen says&#8217;</div>
  <div class="t verse" style="left:11mm;top:37.2mm;width:56mm">&#8220;Where you go I will go and where you lodge,<br>I will lodge, your people shall be my people<br>and your God my God.&#8221;</div>
  <div class="t verse" style="left:46mm;top:48.6mm;width:19.6mm;text-align:right">(Ruth 1:16)</div>
  <div class="t vivaldi" style="left:76mm;top:43mm;font-size:7.4mm;color:{INK}">Charles says&#8217;</div>
  <div class="t verse" style="left:78mm;top:53mm;width:64mm;text-align:center">&#8220;The man who finds a wife finds a treasurer<br>from the Lord&#8221;<br>(Proverbs 18: 22)</div>

  <!-- Middle panel: addressee and doves -->
  {lavender_bouquet()}
  <div class="t to" style="left:73mm;top:88.6mm">To:</div>
  <div class="rule" style="left:70mm;top:94.6mm;width:76mm;height:.45mm;background:#2c2a55"></div>
  {doves()}

  <!-- Bottom panel: title, names and heart -->
  <div class="t wedding" style="left:20mm;top:151mm">wedding</div>
  <svg class="art" viewBox="0 0 30 60" style="left:27.6mm;top:138.5mm;width:7mm;height:15mm" aria-hidden="true">
    <path d="M14,58 L14,4" stroke="#55526b" stroke-width="1.6"/><path d="M11,8 L17,8 M11,14 L17,14" stroke="#55526b" stroke-width="1.2"/>
    <g fill="#55526b"><circle cx="4" cy="30" r=".9"/><circle cx="8" cy="24" r="1.1"/><circle cx="22" cy="28" r="1"/><circle cx="26" cy="20" r=".8"/>
    <circle cx="6" cy="40" r=".8"/><circle cx="24" cy="38" r="1.1"/><circle cx="2" cy="20" r=".7"/><circle cx="28" cy="32" r=".7"/>
    <circle cx="10" cy="34" r=".7"/><circle cx="19" cy="22" r=".7"/><circle cx="20" cy="44" r=".8"/><circle cx="8" cy="46" r=".7"/></g>
  </svg>
  <div class="t invitation" style="left:25.5mm;top:168.2mm">Invitation</div>
  <svg class="art" viewBox="0 0 240 44" style="left:32mm;top:177mm;width:34mm;height:6.2mm;color:#8b8fa0"><use href="#flourish" width="240" height="44"/></svg>

  <div class="t vivaldi" style="left:63mm;top:145mm;font-size:7.6mm;color:#4a3f78">Moureen</div>
  {heart()}
  <div class="t vivaldi" style="left:114mm;top:179mm;font-size:7.6mm;color:#4a3f78">Charles</div>

  {wave()}
</section>'''

BACK = f'''
<section class="page" id="back" aria-label="Back page">

  <svg class="art" viewBox="0 0 240 44" style="left:51mm;top:24.5mm;width:46mm;height:8.4mm;color:{INK}"><use href="#flourish" width="240" height="44"/></svg>
  <div class="t c corsiva" style="top:33.6mm;font-size:4mm">With Joy in our Hearts and Blessings,</div>

  <div class="t vivaldi" style="left:67mm;top:39mm;font-size:12mm;color:{INK}">We</div>
  <svg class="art" viewBox="0 0 140 120" style="left:60mm;top:50mm;width:28mm;height:24mm"><use href="#ringArt" width="140" height="120"/></svg>
  <div class="t vivaldi" style="left:36mm;top:56mm;font-size:7.6mm;color:{INK}">Moureen</div>
  <div class="t vivaldi" style="left:90mm;top:61mm;font-size:7.6mm;color:{INK}">Charles</div>

  <div class="t c corsiva" style="top:77.4mm">The family of the late Alloyce Mukhanga Atsenga and the late Mama Christine Lumbuka</div>
  <div class="t c corsiva" style="top:82mm">Together with</div>
  <div class="t c corsiva" style="top:86.8mm">The family of the late Julius Oti Mundia &amp; Mama Veronica Aduo of Ebusakami</div>
  <div class="t c corsiva light" style="top:91.4mm">Have Pleasure to Invite,</div>
  <div class="t c brush" style="top:97.6mm">Hon. /Prof. /Dr. /Rev. /Mr. /Mrs. /Miss</div>

  <div class="rule" style="left:16mm;top:112.4mm;width:116mm;height:.45mm;background:{INK}"></div>

  <div class="t c corsiva para" style="top:114.6mm">To wedding ceremony of their son Charles Sikobe and their daughter Moureen<br>Mukhanga which will be held on Saturday of 28<sup>th</sup> November, 2026<br>at Mwiyekhe P.A G Assembly, Starting as from 11:00am,<br>And thereafter to a reception at their home Enyaita Ebukame Village.</div>

  <div class="t c corsiva" style="top:144.6mm">Your presence shall be highly appreciated.</div>

  <div class="t rsvp" style="left:16mm;top:172mm">R.S.V.P.<br>Charles<br>0710505736</div>
  <svg class="art" viewBox="0 0 240 44" style="left:44mm;top:171mm;width:44mm;height:8mm;color:#1d6f86"><use href="#flourish" width="240" height="44"/></svg>
  <div class="t rsvp" style="left:94mm;top:169.6mm">R.S.V.P<br>Moureen<br>0723886347</div>
</section>'''

HTML = f'''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Moureen &amp; Charles Wedding Invitation</title>
<style>
/* A5 portrait, two pages: front then back. Everything is positioned in millimetres. */
@page {{ size: 148mm 210mm; margin: 0; }}
* {{ box-sizing: border-box; }}
html, body {{ margin: 0; }}
body {{ background: #d9d6df; -webkit-print-color-adjust: exact; print-color-adjust: exact; }}
.sheet {{ display: flex; flex-wrap: wrap; justify-content: center; gap: 12mm; padding: 12mm; }}
.page {{
  position: relative; width: 148mm; height: 210mm; overflow: hidden; flex: none;
  background: #f4f1f9 url("{PAPER}") center / 100% 100% no-repeat;
  clip-path: {zigzag()};
  filter: drop-shadow(0 2mm 4mm rgba(40, 30, 70, .18));
}}
.art {{ position: absolute; overflow: visible; }}
.art.lav {{ opacity: .9; }}
.rule {{ position: absolute; }}
.t {{ position: absolute; white-space: nowrap; line-height: 1.2; }}
.t.c {{ left: 0; right: 0; text-align: center; }}
.vivaldi {{ font-family: "Vivaldi", "Great Vibes", cursive; font-style: italic; line-height: 1; -webkit-text-stroke: .16mm currentColor; }}
.verse {{ font: italic 700 2.62mm/1.32 "Times New Roman", Times, serif; color: {VERSE}; }}
.to {{ font: 400 3.9mm/1 "Times New Roman", Times, serif; color: #2c2a55; }}
.wedding {{ font: 400 8.6mm/1 "Mistral", "Segoe Script", cursive; color: #55526b; letter-spacing: -.2mm; }}
.invitation {{ font: 400 6.2mm/1 Arial, Helvetica, sans-serif; color: #555a66; }}
.corsiva {{ font: 700 3.62mm/1.25 "Monotype Corsiva", "Apple Chancery", cursive; color: {INK}; }}
.corsiva.light {{ font-weight: 400; }}
.corsiva.para {{ font-size: 3.75mm; line-height: 1.32; }}
.corsiva sup {{ font-size: 60%; line-height: 0; }}
.brush {{ font: 700 4.2mm/1.2 "Brush Script MT", cursive; color: {INK}; letter-spacing: .1mm; }}
.rsvp {{ font: 700 3.5mm/1.34 "Bookman Old Style", Georgia, serif; color: #3d3a73; letter-spacing: .15mm; }}

/* Print / export: pages only, no grey desk, no shadow, one page per sheet */
@media print {{
  body {{ background: none; }}
  .sheet {{ display: block; padding: 0; }}
  .page {{ filter: none; break-after: page; }}
  .page:last-child {{ break-after: auto; }}
}}
/* render.py opens ?only=front / ?only=back to capture one page at a time */
body.only {{ background: transparent; }}
body.only .sheet {{ display: block; padding: 0; }}
body.only .page {{ filter: none; }}
body.only .page:not(.show) {{ display: none; }}
</style>
</head>
<body>
{defs()}
<main class="sheet">
{FRONT}
{BACK}
</main>
<script>
  var only = new URLSearchParams(location.search).get('only');
  if (only) {{ document.body.classList.add('only'); document.getElementById(only).classList.add('show'); }}
</script>
</body>
</html>
'''

if __name__ == '__main__':
    out = os.path.join(HERE, 'invitation.html')
    io.open(out, 'w', encoding='utf-8', newline='\n').write(HTML)
    print('wrote', out)
