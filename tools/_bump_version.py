import pathlib, sys
root = pathlib.Path(r'c:\Users\HP\Documents\Eurasian\Eurasien\static-site')
old, new = 'site-02.js?v=p6fix2', 'site-02.js?v=p6fix3'
n = 0
for p in root.rglob('*.html'):
    t = p.read_text(encoding='utf-8')
    if old in t:
        p.write_text(t.replace(old, new), encoding='utf-8')
        n += 1
        print('updated:', p.relative_to(root))
print(f'\nTotal: {n}')
