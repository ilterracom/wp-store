async function updateDownloadLink() {
  try {
    const response = await fetch('https://api.github.com/repos/ilterracom/wp-store/git/matching-refs/tags/wp-store/');
    const tags = await response.json();
    if (Array.isArray(tags) && tags.length) {
      const latest = tags.sort((a, b) => a.ref.localeCompare(b.ref)).pop();
      const tag = latest.ref.replace('refs/tags/', '');
      const url = `https://github.com/ilterracom/wp-store/archive/refs/tags/${tag}.zip`;
      const btn = document.getElementById('download-link');
      btn.href = url;
      btn.textContent = `Download ${tag}`;
    }
  } catch (err) {
    console.error('Failed to retrieve the latest plugin version', err);
  }
}

window.addEventListener('DOMContentLoaded', updateDownloadLink);
