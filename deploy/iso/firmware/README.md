# فریم‌ور تیونرهای DVB-T USB

این دو فایل برای تیونرهای **ITE IT9135** هستند — همان خانواده‌ای که بیشتر
برندهای MyGica / Geniatech می‌فروشند (`048d:9005`، `048d:9006`، `048d:9135`).

| فایل | چیپ | اندازه | sha256 |
|------|-----|--------|--------|
| `dvb-usb-it9135-01.fw` | IT9135 نسخهٔ ۱ (Ax) | 8128 | `2ce2f0da0054d64f14a5ec4a52f5e4ade2fcd9340f15df19516526eb3f6cae9a` |
| `dvb-usb-it9135-02.fw` | IT9135 نسخهٔ ۲ (Bx) | 5834 | `b8ac24c7c2f8aae7a02c7b564fcb0278b962bb2523b75a3d1f6d2cc01bd7affd` |

## چرا داخل ISO هستند

درایور `dvb-usb-it913x` در هستهٔ اوبونتو هست، ولی فریم‌ور جزو
`linux-firmware` نیست. بدون این فایل‌ها `/dev/dvb/adapter0` ساخته نمی‌شود و
در `dmesg` می‌آید:

```
dvb-usb: did not find the firmware file (dvb-usb-it9135-02.fw)
```

`user-data` این دو فایل را در نصب به `/lib/firmware/` کپی می‌کند، پس نصب روی
هتلِ بدون اینترنت هم تیونر دارد.

## منبع و لایسنس

از مخزن رسمی `linux-firmware` گرفته شده‌اند:

```
https://git.kernel.org/pub/scm/linux/kernel/git/firmware/linux-firmware.git/plain/dvb-usb-it9135-01.fw
https://git.kernel.org/pub/scm/linux/kernel/git/firmware/linux-firmware.git/plain/dvb-usb-it9135-02.fw
```

حق نشر ITE Tech. Inc. و با اجازهٔ بازتوزیع (همان متن `LICENCE.it9135` در
`linux-firmware`):

> Permission to use, copy, modify, and/or distribute this software for any
> purpose with or without fee is hereby granted, provided that the above
> copyright notice and this permission notice appear in all copies.

برای تیونر دیگری (مثلاً Siano یا AF9035) فایل مربوط را از همان مخزن بردارید و
یک خط `install` در `late-commands` اضافه کنید.
