// utils.js

// Fisher-Yates shuffle
export function shuffleArray(array) {
  const copy = [...array];
  for (let i = copy.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [copy[i], copy[j]] = [copy[j], copy[i]];
  }
  return copy;
}

// Pull a random item without repeating until all are seen
export function getUniqueRandomItem(key, sourceArray) {
  const used = JSON.parse(localStorage.getItem(key)) || [];
  const remaining = sourceArray.filter(item => !used.includes(item));

  if (remaining.length === 0) {
    localStorage.removeItem(key); // reset once all are used
    return getUniqueRandomItem(key, sourceArray); // recursive retry
  }

  const pick = remaining[Math.floor(Math.random() * remaining.length)];
  localStorage.setItem(key, JSON.stringify([...used, pick]));
  return pick;
}

// Get a smart group of X different entries (default 3)
export function getSmartRandomGroup(array, count = 3) {
  const shuffled = shuffleArray(array);
  return shuffled.slice(0, count);
}

// Clean + format US phone number
export function cleanPhoneInput(input) {
  const cleaned = input.replace(/\D/g, '');
  return cleaned.length >= 10
    ? `(${cleaned.slice(0,3)}) ${cleaned.slice(3,6)}-${cleaned.slice(6,10)}`
    : input;
}

// Reset memory (dev only)
export function resetLocalMomentMemory(key) {
  localStorage.removeItem(key);
}

