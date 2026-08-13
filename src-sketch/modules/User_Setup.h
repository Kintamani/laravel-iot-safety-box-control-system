// User setup for ESP32 + ST7789 240x240 via SPI

#define USER_SETUP_INFO "ESP32_ST7789_240x240_CUSTOM"

#define ST7789_DRIVER

#define TFT_WIDTH  240
#define TFT_HEIGHT 240

// Many 240x240 ST7789 panels need inversion enabled.
#define TFT_INVERSION_ON

// Uncomment if colours look swapped after the panel is working.
// #define TFT_RGB_ORDER TFT_BGR

#define TFT_MOSI 23
#define TFT_SCLK 18
#define TFT_MISO -1

#define TFT_CS   -1
#define TFT_DC   27
#define TFT_RST  26

#define LOAD_GLCD
#define LOAD_FONT2
#define LOAD_FONT4
#define LOAD_FONT6
#define LOAD_FONT7
#define LOAD_FONT8
#define LOAD_GFXFF
#define SMOOTH_FONT

// Conservative SPI clock for breadboard wiring.
#define SPI_FREQUENCY       20000000
#define SPI_READ_FREQUENCY  10000000
#define SPI_TOUCH_FREQUENCY 2500000
