// Eleventy computed data asılılıqları tapmaq üçün funksiyaları əvvəlcə proxy data ilə çağırır;
// həmin keçiddə xətaya dözümlü olmaq üçün sarğı. Son keçiddə real dəyər hesablanır.
export const safe = (fn, fallback) => (d) => {
  try {
    return fn(d);
  } catch (e) {
    return fallback;
  }
};
