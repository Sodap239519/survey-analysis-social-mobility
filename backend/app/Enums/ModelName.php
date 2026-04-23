<?php

namespace App\Enums;

/**
 * Single source of truth for all canonical "โมเดลแก้จน" names.
 *
 * When adding or renaming a model, update ONLY this file.
 * The validation rule in SurveyResponseController references VALID_NAMES.
 */
class ModelName
{
    /**
     * All valid canonical model name strings.
     * Order matches the frontend constants/modelNames.js groups.
     */
    public const VALID_NAMES = [
        // Local Content
        'โมเดลไข่ผำ แก้จน',
        'โมเดลกล้าไม้แก้จน',
        'โมเดลผักยกแคร่สร้างสุข',
        'โมเดล Korat Handy Care',
        'โมเดลผักไร้ดิน กินปลอดภัย',
        'โมเดลดาวเรืองสร้างอาชีพ ชีวิตยั่งยืน',

        // Pro-poor Value Chain
        'โมเดลมหัศจรรย์ไข่ผำ',
        'โมเดลมะขามป้อม',
        'โมเดล Veggies to Value ผักคุณค่า พายั่งยืน',
        'โมเดลภาคี พามี',

        // Social Safety Net
        'กองทุนแก้จน',
        'ตะไคร้ดี ลดหนี้ชุมชน',
        'ผักเขียว เหนี่ยวทรัพย์',

        // Area Based Industries
        'โมเดลพริกจินดา',
    ];
}
