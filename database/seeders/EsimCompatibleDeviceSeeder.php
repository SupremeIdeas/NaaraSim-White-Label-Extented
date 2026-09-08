<?php

namespace Database\Seeders;

use App\Models\EsimCompatibleDevice;
use Illuminate\Database\Seeder;

/**
 * eSIM-compatible device catalogue (esim_upgrade Part 2). Seeded from the spec's
 * device list. Idempotent — safe to re-run; admin can extend later. Grouped as
 * [os_group][category] => [brand => [device, …]] for compact, maintainable entry.
 */
class EsimCompatibleDeviceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogue() as $os => $categories) {
            foreach ($categories as $category => $brands) {
                foreach ($brands as $brand => $devices) {
                    foreach ($devices as $device) {
                        EsimCompatibleDevice::firstOrCreate([
                            'os_group' => $os,
                            'category' => $category,
                            'device_name' => $device,
                        ], ['brand' => $brand]);
                    }
                }
            }
        }
    }

    /** @return array<string, array<string, array<string, list<string>>>> */
    private function catalogue(): array
    {
        return [
            'apple' => [
                'phone' => ['Apple' => [
                    'iPhone 11', 'iPhone 11 Pro', 'iPhone 11 Pro Max', 'iPhone XR', 'iPhone XS', 'iPhone XS Max',
                    'iPhone SE (2nd gen)', 'iPhone SE (3rd gen)', 'iPhone 12', 'iPhone 12 Mini', 'iPhone 12 Pro', 'iPhone 12 Pro Max',
                    'iPhone 13', 'iPhone 13 Mini', 'iPhone 13 Pro', 'iPhone 13 Pro Max', 'iPhone 14', 'iPhone 14 Plus', 'iPhone 14 Pro', 'iPhone 14 Pro Max',
                    'iPhone 15', 'iPhone 15 Plus', 'iPhone 15 Pro', 'iPhone 15 Pro Max', 'iPhone 16', 'iPhone 16 Plus', 'iPhone 16 Pro', 'iPhone 16 Pro Max', 'iPhone 16e',
                    'iPhone 17', 'iPhone 17 Pro', 'iPhone 17 Pro Max', 'iPhone 17 Air',
                ]],
                'tablet' => ['Apple' => [
                    'iPad (8th gen)', 'iPad (9th gen)', 'iPad (10th gen)', 'iPad A16 (11th gen)', 'iPad Mini (6th gen)', 'iPad Mini (7th gen)',
                    'iPad Air (5th gen)', 'iPad Air (M2) 11"', 'iPad Air (M2) 13"', 'iPad Air (M3) 11"', 'iPad Air (M3) 13"',
                    'iPad Pro (1st gen)', 'iPad Pro (2nd gen)', 'iPad Pro (M4)',
                ]],
                'watch' => ['Apple' => [
                    'Apple Watch Series 3 & 4', 'Apple Watch Series 10', 'Apple Watch Series 11', 'Apple Watch SE', 'Apple Watch SE 2', 'Apple Watch SE 3',
                    'Apple Watch Ultra 2', 'Apple Watch Ultra 3', 'Apple Watch Ultra LTE',
                ]],
            ],
            'android' => [
                'phone' => [
                    'Samsung' => [
                        'Galaxy S20', 'Galaxy S20+', 'Galaxy S20 Ultra', 'Galaxy Note 20', 'Galaxy Note 20 Ultra', 'Galaxy Note 20 Ultra 5G',
                        'Galaxy Z Flip', 'Galaxy Z Flip3 5G', 'Galaxy Z Flip4', 'Galaxy Z Flip 5', 'Galaxy Z Flip 6', 'Galaxy Z Flip7',
                        'Galaxy Z Fold', 'Galaxy Z Fold 2', 'Galaxy Z Fold 3 5G', 'Galaxy Z Fold4', 'Galaxy Z Fold 5', 'Galaxy Z Fold 6', 'Galaxy Z Fold7',
                        'Galaxy S21 5G', 'Galaxy S22 5G', 'Galaxy S21 Ultra 5G', 'Galaxy S22 Ultra 5G', 'Galaxy S21+ 5G', 'Galaxy S22+ 5G',
                        'Galaxy S23', 'Galaxy S23+', 'Galaxy S23 Ultra', 'Galaxy S23 FE', 'Galaxy S24', 'Galaxy S24+', 'Galaxy S24 Ultra', 'Galaxy S24 FE',
                        'Galaxy S25', 'Galaxy S25+', 'Galaxy S25 Edge', 'Galaxy S25 Slim', 'Galaxy S25 Ultra',
                        'Galaxy A23 5G', 'Galaxy A35 5G', 'Galaxy A36', 'Galaxy A54 5G', 'Galaxy A55 5G', 'Galaxy A56', 'Galaxy XCover7', 'Galaxy XCover7 Pro',
                    ],
                    'Google Pixel' => [
                        'Pixel 2', 'Pixel 2 XL', 'Pixel 3', 'Pixel 3 XL', 'Pixel 3A', 'Pixel 3A XL', 'Pixel 4', 'Pixel 4 XL', 'Pixel 4a', 'Pixel 4a 5G',
                        'Pixel 5', 'Pixel 5a', 'Pixel 5a 5G', 'Pixel 6', 'Pixel 6 Pro', 'Pixel 6a', 'Pixel 7', 'Pixel 7 Pro', 'Pixel 7a',
                        'Pixel 8', 'Pixel 8a', 'Pixel 8 Pro', 'Pixel 9', 'Pixel 9a', 'Pixel 9 Pro', 'Pixel 9 Pro XL', 'Pixel 9 Pro Fold',
                        'Pixel 10', 'Pixel 10 Pro', 'Pixel 10 Pro XL', 'Pixel 10 Pro Fold', 'Pixel Fold',
                    ],
                    'Motorola' => [
                        'Edge+', 'Edge+ (2023)', 'Edge (2022)', 'Edge (2023)', 'Edge (2024)', 'Edge 40', 'Edge 40 Neo', 'Edge 40 Pro',
                        'Edge 50', 'Edge 50 Fusion', 'Edge 50 Neo', 'Edge 50 Pro', 'Edge 50 Ultra', 'Edge 60', 'Edge 60 Fusion', 'Edge 60 Pro', 'Edge 60 Stylus',
                        'Razr 2024', 'Razr+ 2024', 'Razr 40', 'Razr 40 Ultra', 'Razr 50', 'Razr 50 Ultra', 'Razr 60', 'Razr 60 Ultra', 'Razr Ultra 2025', 'ThinkPhone 25',
                    ],
                    'Xiaomi' => [
                        'Xiaomi 12T Pro', 'Xiaomi 13', 'Xiaomi 13 Lite', 'Xiaomi 13 Pro', 'Xiaomi 13T', 'Xiaomi 13T Pro', 'Xiaomi 14', 'Xiaomi 14 Pro',
                        'Xiaomi 14T', 'Xiaomi 14T Pro', 'Xiaomi 15', 'Xiaomi 15 Ultra', 'Poco X7', 'Redmi Note 13 Pro', 'Redmi Note 13 Pro+', 'Redmi Note 14 Pro 5G', 'Redmi Note 14 Pro+ 5G',
                    ],
                    'Huawei / Honor' => [
                        'Huawei P40', 'Huawei P40 Pro', 'Huawei P60', 'Huawei P60 Pro', 'Huawei Mate 40 Pro', 'Huawei Mate 60', 'Huawei Mate 60 Pro', 'Huawei Pura 70 Pro',
                        'Honor 90', 'Honor 200', 'Honor 200 Pro', 'Honor 400', 'Honor 400 Pro', 'Honor Magic6 Pro', 'Honor Magic7 Lite', 'Honor Magic V3',
                    ],
                    'Oppo / OnePlus' => [
                        'Oppo Find N5', 'Oppo Find X8', 'Oppo Find X8 Pro', 'Oppo Reno14', 'Oppo Reno14 Pro',
                        'OnePlus 11', 'OnePlus 12', 'OnePlus 13', 'OnePlus 13R', 'OnePlus 13T', 'OnePlus Open',
                    ],
                    'Sony / Others' => [
                        'Sony Xperia 1 VI', 'Sony Xperia 1 VII', 'Sony Xperia 5 V', 'Sony Xperia 10 VI', 'Nothing Phone (3a) Pro', 'Fairphone 5', 'Fairphone 6',
                    ],
                ],
                'tablet' => ['Samsung' => [
                    'Galaxy Tab Active5', 'Galaxy Tab Active5 Pro 5G', 'Galaxy Tab S9 5G', 'Galaxy Tab S9+ 5G', 'Galaxy Tab S9 Ultra 5G',
                    'Galaxy Tab S9 FE 5G', 'Galaxy Tab S10', 'Galaxy Tab S10+', 'Galaxy Tab S10 Ultra 5G', 'Galaxy Tab S11', 'Galaxy Tab S11 Ultra 5G',
                ]],
            ],
            'others' => [
                'laptop' => [
                    'Dell (Latitude)' => ['Latitude 5410', 'Latitude 7210 2-in-1', 'Latitude 7310', 'Latitude 7410', 'Latitude 9410', 'Latitude 9510'],
                    'Lenovo / ThinkPad' => ['Flex 5G', 'ThinkPad X1 Carbon Gen 9', 'ThinkPad X1 Fold', 'ThinkPad X1 Nano', 'ThinkPad X12 Detachable'],
                    'HP' => ['Elitebook G5', 'Probook G5', 'Zbook G5', 'Spectre Folio'],
                    'Acer / ASUS' => ['Acer Swift 3', 'Acer Swift 7', 'ASUS NovaGo TP370QL', 'ASUS Vivobook Flip 14'],
                ],
                'tablet' => ['Microsoft' => ['Surface Pro 9', 'Surface Go 3', 'Surface Pro X', 'Surface Duo', 'Surface Duo 2']],
            ],
        ];
    }
}
