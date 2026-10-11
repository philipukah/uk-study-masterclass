// Retain Google click attribution for registration without storing contact data.
(function (window) {
  'use strict';
  var keys = ['gclid', 'gbraid', 'wbraid'];
  var storageKey = 'masterclass_google_click';
  var query = new URLSearchParams(window.location.search);
  var current = {};
  var hasCurrent = false;
  keys.forEach(function (key) {
    current[key] = (query.get(key) || '').trim().slice(0, 500);
    if (current[key]) hasCurrent = true;
  });

  var captured = current;
  try {
    if (hasCurrent) {
      // A new Google click replaces the whole set, avoiding mixed click IDs.
      window.sessionStorage.setItem(storageKey, JSON.stringify(current));
    } else {
      var saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}');
      if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
        keys.forEach(function (key) {
          captured[key] = typeof saved[key] === 'string' ? saved[key].slice(0, 500) : '';
        });
      }
    }
  } catch (_) {
    // Blocked storage or malformed stored data must never block registration.
  }

  window.getMasterclassGoogleAttribution = function () {
    return { gclid: captured.gclid || '', gbraid: captured.gbraid || '', wbraid: captured.wbraid || '' };
  };
}(window));
