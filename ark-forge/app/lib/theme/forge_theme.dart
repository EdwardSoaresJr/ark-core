/// ARK Forge brand colors — aligned with ARK ecosystem cerulean.
library;

import 'package:flutter/material.dart';

abstract final class ForgeTheme {
  static const Color arkBlue = Color(0xFF0099CC);
  static const Color surface = Color(0xFF0F1419);
  static const Color card = Color(0xFF1A2332);
  static const Color border = Color(0xFF2A3544);
  static const Color textPrimary = Color(0xFFE8EEF4);
  static const Color textMuted = Color(0xFF8B9AAB);
  static const Color success = Color(0xFF3DAA7D);
  static const Color warning = Color(0xFFD4A017);

  static ThemeData dark() {
    return ThemeData(
      brightness: Brightness.dark,
      scaffoldBackgroundColor: surface,
      colorScheme: const ColorScheme.dark(
        primary: arkBlue,
        surface: card,
        onSurface: textPrimary,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: surface,
        foregroundColor: textPrimary,
        elevation: 0,
      ),
      cardTheme: const CardThemeData(
        color: card,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(8)),
          side: BorderSide(color: border),
        ),
      ),
      dividerColor: border,
      fontFamily: 'Segoe UI',
    );
  }
}
