/**
 * Single source of truth for all canonical "โมเดลแก้จน" names.
 *
 * Each entry has:
 *   group  – display label for the <optgroup>
 *   value  – the exact string stored in the database / sent to the API
 *   label  – human-readable text shown in the dropdown (usually same as value)
 *
 * When adding or renaming a model, update ONLY this file.
 * All dropdowns in the UI import from here automatically.
 */
export const MODEL_NAME_GROUPS = [
  {
    group: 'Local Content',
    models: [
      'โมเดลไข่ผำ แก้จน',
      'โมเดลกล้าไม้แก้จน',
      'โมเดลผักยกแคร่สร้างสุข',
      'โมเดล Korat Handy Care',
      'โมเดลผักไร้ดิน กินปลอดภัย',
      'โมเดลดาวเรืองสร้างอาชีพ ชีวิตยั่งยืน',
    ],
  },
  {
    group: 'Pro-poor Value Chain',
    models: [
      'โมเดลมหัศจรรย์ไข่ผำ',
      'โมเดลมะขามป้อม',
      'โมเดล Veggies to Value ผักคุณค่า พายั่งยืน',
      'โมเดลภาคี พามี',
    ],
  },
  {
    group: 'Social Safety Net',
    models: [
      'กองทุนแก้จน',
      'ตะไคร้ดี ลดหนี้ชุมชน',
      'ผักเขียว เหนี่ยวทรัพย์',
    ],
  },
  {
    group: 'Area Based Industries',
    models: [
      'โมเดลพริกจินดา',
    ],
  },
];

/**
 * Flat list of all valid model name strings.
 * Useful for validation and lookups.
 */
export const ALL_MODEL_NAMES = MODEL_NAME_GROUPS.flatMap((g) => g.models);
