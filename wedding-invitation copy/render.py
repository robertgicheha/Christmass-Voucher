"""Exports invitation.html to print-quality files.

  invitation.pdf             vector A5, 2 pages (front, back): text and artwork stay sharp at any zoom
  invitation-front.png/.jpg  300 dpi, 1748 x 2480 px (PNG has a transparent background outside the pinked edge)
  invitation-back.png/.jpg   300 dpi, 1748 x 2480 px (JPG on white)

Needs Google Chrome and Pillow. Run build.py first.
"""
import os
import subprocess
import tempfile

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
CHROME = r'C:\Program Files\Google\Chrome\Application\chrome.exe'
URL = 'file:///' + os.path.join(HERE, 'invitation.html').replace('\\', '/')
DPI = 300
PX_W, PX_H = round(148 / 25.4 * DPI), round(210 / 25.4 * DPI)   # A5 at 300 dpi: 1748 x 2480
SCALE = PX_W / (148 / 25.4 * 96)                                   # CSS px -> device px


def chrome(*args):
    subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--hide-scrollbars',
                    '--virtual-time-budget=4000', *args], check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def main():
    pdf = os.path.join(HERE, 'invitation.pdf')
    chrome('--no-pdf-header-footer', '--print-to-pdf=' + pdf, URL)
    print('wrote', pdf)

    for page in ('front', 'back'):
        with tempfile.TemporaryDirectory() as tmp:
            shot = os.path.join(tmp, 'shot.png')
            chrome(f'--force-device-scale-factor={SCALE}', '--window-size=560,794',
                   '--default-background-color=00000000', '--screenshot=' + shot, f'{URL}?only={page}')
            img = Image.open(shot).convert('RGBA').crop((0, 0, PX_W, PX_H))

        png = os.path.join(HERE, f'invitation-{page}.png')
        img.save(png, dpi=(DPI, DPI), optimize=True)

        white = Image.new('RGB', img.size, (255, 255, 255))
        white.paste(img, mask=img.getchannel('A'))
        jpg = os.path.join(HERE, f'invitation-{page}.jpg')
        white.save(jpg, quality=95, subsampling=0, dpi=(DPI, DPI), optimize=True)
        print('wrote', png, 'and', jpg, img.size)


if __name__ == '__main__':
    main()
