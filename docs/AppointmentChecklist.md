## 9.5.4. Checklist de validació de la gestió de cites abans del PR

Aquest checklist s’utilitza per verificar que totes les opcions principals de la gestió de cites funcionen correctament abans de validar el commit i crear el Pull Request.

---

### 1. Visualització del calendari

| Validació | Resultat |
|---|---|
| La vista diària mostra tots els boxes correctament | ☐ OK ☐ KO |
| La vista setmanal mostra les cites filtrades per box i doctor | ☐ OK ☐ KO |
| Les cites es mostren al box corresponent | ☐ OK ☐ KO |
| Es mostra correctament l’hora d’inici i fi de la cita | ☐ OK ☐ KO |
| Es mostra el pacient assignat | ☐ OK ☐ KO |
| Es mostra el doctor assignat | ☐ OK ☐ KO |
| Es mostra l’etiqueta de primera visita, urgència o patologia | ☐ OK ☐ KO |
| Es mostra la marca d’al·lèrgia en vermell quan correspon | ☐ OK ☐ KO |

---

### 2. Creació de cites

| Validació | Resultat |
|---|---|
| Es pot crear una cita per a un pacient donat d’alta | ☐ OK ☐ KO |
| Es pot seleccionar data, hora, doctor i box | ☐ OK ☐ KO |
| La durada per defecte és de 30 minuts | ☐ OK ☐ KO |
| La primera visita assigna automàticament 60 minuts | ☐ OK ☐ KO |
| La urgència assigna automàticament 30 minuts | ☐ OK ☐ KO |
| La patologia assigna la durada configurada | ☐ OK ☐ KO |
| La durada es pot modificar manualment | ☐ OK ☐ KO |
| La cita queda guardada correctament a la base de dades | ☐ OK ☐ KO |
| La cita apareix al calendari després de guardar | ☐ OK ☐ KO |

---

### 3. Edició de cites

| Validació | Resultat |
|---|---|
| Es pot obrir el formulari d’edició des del calendari | ☐ OK ☐ KO |
| El formulari carrega les dades existents de la cita | ☐ OK ☐ KO |
| Es pot modificar la data i l’hora | ☐ OK ☐ KO |
| Es pot modificar el doctor | ☐ OK ☐ KO |
| Es pot modificar el box | ☐ OK ☐ KO |
| Es pot modificar la durada | ☐ OK ☐ KO |
| Es pot modificar la patologia o tipus de visita | ☐ OK ☐ KO |
| Els canvis es guarden correctament | ☐ OK ☐ KO |
| El calendari s’actualitza després de l’edició | ☐ OK ☐ KO |

---

### 4. Validacions de disponibilitat

| Validació | Resultat |
|---|---|
| En seleccionar una data, només es mostren doctors disponibles | ☐ OK ☐ KO |
| No permet seleccionar un doctor fora del seu horari laboral | ☐ OK ☐ KO |
| Detecta conflicte si el box ja està ocupat | ☐ OK ☐ KO |
| Evita guardar dues cites al mateix box i mateixa franja horària | ☐ OK ☐ KO |
| Detecta si el mateix doctor ja té una cita en un altre box | ☐ OK ☐ KO |
| Evita guardar cites solapades pel mateix doctor | ☐ OK ☐ KO |
| Té en compte el temps de neteja del box en les validacions | ☐ OK ☐ KO |

---

### 5. Gestió de pacients, tractaments i patologies

| Validació | Resultat |
|---|---|
| El sistema detecta si el pacient té tractaments actius | ☐ OK ☐ KO |
| Es mostren les patologies pendents si hi ha seguiment clínic | ☐ OK ☐ KO |
| Es pot crear una cita associada a un tractament existent | ☐ OK ☐ KO |
| Es pot crear una cita associada només a una patologia | ☐ OK ☐ KO |
| Es pot seleccionar l’opció “Revisió” si no es coneix la patologia exacta | ☐ OK ☐ KO |
| La cita manté la continuïtat clínica del pacient | ☐ OK ☐ KO |

---

### 6. Al·lèrgies

| Validació | Resultat |
|---|---|
| El sistema detecta si el pacient té al·lèrgies destacades | ☐ OK ☐ KO |
| Es mostra una alerta en crear o editar la cita | ☐ OK ☐ KO |
| Es mostra la marca visual d’al·lèrgia al calendari | ☐ OK ☐ KO |
| La informació d’al·lèrgies és visible abans d’obrir la cita | ☐ OK ☐ KO |

---

### 7. Estats de la cita

| Validació | Resultat |
|---|---|
| Es crea la cita amb estat “Programada” quan el consentiment està signat | ☐ OK ☐ KO |
| Es crea la cita amb estat “Falta consentiment” si no hi ha consentiment | ☐ OK ☐ KO |
| Es pot canviar l’estat a “Confirmada” | ☐ OK ☐ KO |
| Es pot canviar l’estat a “Arribada” | ☐ OK ☐ KO |
| En obrir l’odontograma, l’estat canvia a “En curs” | ☐ OK ☐ KO |
| Es pot finalitzar la visita correctament | ☐ OK ☐ KO |
| L’estat actualitzat es reflecteix al calendari | ☐ OK ☐ KO |

---

### 8. Odontograma

| Validació | Resultat |
|---|---|
| El botó “Obrir” accedeix a l’odontograma del pacient | ☐ OK ☐ KO |
| Sempre s’obre el mateix odontograma evolutiu del pacient | ☐ OK ☐ KO |
| L’odontograma conserva les dades de visites anteriors | ☐ OK ☐ KO |
| Els canvis fets en una visita queden disponibles en visites posteriors | ☐ OK ☐ KO |

---

### 9. Reserva de neteja del box

| Validació | Resultat |
|---|---|
| Després de cada cita es crea automàticament un bloc de neteja | ☐ OK ☐ KO |
| El bloc de neteja és de 5 minuts per defecte | ☐ OK ☐ KO |
| Es pot ajustar el temps de neteja a 10 o 15 minuts | ☐ OK ☐ KO |
| El temps de neteja es mostra al calendari | ☐ OK ☐ KO |
| El temps de neteja es té en compte en els conflictes de disponibilitat | ☐ OK ☐ KO |

---

### 10. Accions ràpides des del calendari

| Validació | Resultat |
|---|---|
| Es pot editar una cita des del calendari | ☐ OK ☐ KO |
| Es pot obrir l’odontograma des del calendari | ☐ OK ☐ KO |
| Es pot canviar l’estat de la cita des del calendari | ☐ OK ☐ KO |
| Es pot finalitzar una visita des del calendari | ☐ OK ☐ KO |
| Les accions actualitzen correctament la informació mostrada | ☐ OK ☐ KO |

---

### 11. Validació final del commit

| Validació | Resultat |
|---|---|
| No apareixen errors en consola del navegador | ☐ OK ☐ KO |
| No apareixen errors al backend | ☐ OK ☐ KO |
| Les dades es guarden correctament a la base de dades | ☐ OK ☐ KO |
| Les modificacions es mantenen després de refrescar la pàgina | ☐ OK ☐ KO |
| No s’han trencat funcionalitats anteriors | ☐ OK ☐ KO |
| El commit pot considerar-se vàlid per crear el PR | ☐ OK ☐ KO |

---

**Conclusió de validació:**  
Commit validat per PR: ☐ Sí ☐ No  

**Observacions:**  