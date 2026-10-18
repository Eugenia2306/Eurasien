const form = $('Drop LinkedIn URL').first().json;
const url = String(form['LinkedIn URL'] || form.linkedin_url || '').trim();
const raw = $input.first().json;
let html = '';
if (typeof raw === 'string') html = raw;
else if (raw && typeof raw.data === 'string') html = raw.data;
else html = String(raw.body || raw.data || '');

function meta(key) {
  const keys = [key];
  for (const k of keys) {
    const patterns = [
      'property="' + k + '" content="',
      "property='" + k + "' content='",
      'content="',
    ];
    const prop = 'property="' + k + '"';
    const idx = html.indexOf(prop);
    if (idx === -1) continue;
    const slice = html.slice(Math.max(0, idx - 80), idx + 400);
    const m = slice.match(/content="([^"]*)"/);
    if (m) return m[1];
  }
  const named = html.match(new RegExp('name="' + key + '"\\s+content="([^"]*)"'));
  return named ? named[1] : '';
}

function decode(s) {
  return String(s || '')
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/\u2014/g, ', ')
    .replace(/\u2013/g, '-')
    .replace(/\s+/g, ' ')
    .trim();
}

const title = decode(meta('og:title'));
const text = decode(meta('og:description') || meta('description'));
if (!url || text.length < 40) {
  throw new Error('LinkedIn did not give enough public text. Use a public company post URL.');
}
return [{ json: { url, title: title || 'LinkedIn-Beitrag', text } }];
