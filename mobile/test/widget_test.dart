import 'package:durian_farm_mobile/main.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('shows login screen when no auth session is stored', (
    WidgetTester tester,
  ) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(const DurianFarmApp());
    await tester.pumpAndSettle();

    expect(find.text('Durian Farm Mobile'), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
  });
}
