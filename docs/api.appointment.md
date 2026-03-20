
## Cuando creamos una cita, necesitamos:
Paciente + + Fecha + doctor/box +
tratamiento y/o patología ||
o primera visita ||
o visita a diagnosticar ||
o vistita urgente 

## Cuando abrimos la cita, es cuando se crea si no existe el odontograma
Para crear Odontograma tenemos dos casuísticas:
Crear un odontograma asociado a un tratamiento/patología
Crear un odontograma por patología sin tratamiento

## Cuando abrimos odontograma porque existe o porque lo acabamos de crear:
Agenda necesita del Odontograma para abrir la cita:
En PatientController->getLastIdOdontogram($patient);
En OdontogramController->createNewVisitOdontogram($patient, $appointment);
En PatientController->saveLastIdOdontogram($patient, $odontogramId);

## Cuando se crea un tratamiento
Si el paciente necesita un tratamiento, se necesitan instrucciones siempre del Odontólogo que se dejan en las notas de la primera visita o visita a diagnosticar o visita urgente.