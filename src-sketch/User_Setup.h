// User setup for ESP32 + ST7789 240x240 (GMT130) via SPI

#define USER_SETUP_INFO "ESP32_ST7789_240x240"

// Driver
#define ST7789_DRIVER

// Display resolution
#define TFT_WIDTH 240
#define TFT_HEIGHT 240

// SPI frequency
#define SPI_FREQUENCY 20000000

// ESP32 SPI pins (hardware SPI)
#define TFT_MOSI 23
#define TFT_SCLK 18
#define TFT_MISO -1

// Control pins (avoid boot strapping pins)
#define TFT_CS -1
#define TFT_DC 27
#define TFT_RST 26

// Optional backlight control
// #define TFT_BL  33
// #define TFT_BACKLIGHT_ON HIGH
