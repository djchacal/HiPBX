��    6      �  I   |      �  �  �     #  
   9     D     V     h     w     �  7   �     �  	   �     �     o     �     �  $   �  '   �     �     �            .        L  
   R     ]     l     {  
   �     �     �  &   �  	   �  0   �     	  -   
	     8	  o   >	  �   �	     6
  1   =
  �  o
     	          $     1  :   D          �  &   �  	   �     �     �     �  e  �  >  J  *   �     �     �  !   �                0  	   I     S  	   a  �   k  )        B     S  .   e  -   �     �     �     �     �  <   �     +  
   1     <     K     ]     e     t     �  /   �  	   �  1   �  	   �  &        ,  p   3  �   �     4  .   ;  �  j     $     -     C     R  L   g     �     �  2   �       	             )               6   
   -       #                           	   *                   3   4             )             %       1          ,          .   2                 '                5   !           (   $   &      "              +          /                   0    A Lookup Source let you specify a source for resolving numeric CallerIDs of incoming calls, you can then link an Inbound route to a specific CID source. This way you will have more detailed CDR reports with information taken directly from your CRM. You can also install the phonebook module to have a small number <-> name association. Pay attention, name lookup may slow down your PBX Add CID Lookup Source Add Source CID Lookup Source CID Lookup source Cache results: CallerID Lookup CallerID Lookup Sources Checking for cidlookup field in core's incoming table.. Database name Database: Decide whether or not cache the results to astDB; it will overwrite present values. It does not affect Internal source behavior Delete CID Lookup source ERROR: failed:  Edit Source Enter a description for this source. FATAL: failed to transform old routes:  HTTP Host name or IP address Host: Internal Migrating channel routing to Zap DID routing.. MySQL MySQL Host MySQL Password MySQL Username None Not Needed Not yet implemented OK Password to use in HTTP authentication Password: Path of the file to GET<br/>e.g.: /cidlookup.php Path: Port HTTP server is listening at (default 80) Port: Query string, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: number=[NUMBER]&source=crm Query, special token '[NUMBER]' will be replaced with caller number<br/>e.g.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Query: Removing deprecated channel field from incoming.. Select the source type, you can choose between:<ul><li>Internal: use astdb as lookup source, use phonebook module to populate it</li><li>ENUM: Use DNS to lookup caller names, it uses ENUM lookup zones as configured in enum.conf</li><li>HTTP: It executes an HTTP GET passing the caller number as argument to retrieve the correct name</li><li>MySQL: It queries a MySQL database to retrieve caller name</li></ul> Source Source Description: Source type: Source: %s (id %s) Sources can be added in Caller Name Lookup Sources section Submit Changes SugarCRM Username to use in HTTP authentication Username: deleted not present removed Project-Id-Version: 2.5
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2010-06-22 19:14+0200
PO-Revision-Date: 2011-04-14 00:00+0100
Last-Translator: Francesco Romano <francesco.romano@alteclab.it>
Language-Team: Italian
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
X-Poedit-Language: Italian
X-Poedit-Country: ITALY
 Da qui è possibile specificare una sorgente per la risoluzione del Numero Identificativo Chiamante nelle chiamate in entrata; successivamente si potrà creare un collegamento nelle Rotte in Entrata ad una delle sorgenti qui specificate. In questo modo si avranno le informazioni sui clienti prese direttamente dal proprio CRM e visualizzabili nei Rapporti Chiamate. Si può installare anche il modulo Rubrica che permette di associare in maniera veloce il numero di telefono al nome in Rubrica. Stare molto attenti però, la Risoluzione dei nomi potrebbe rallentare il PBX. Aggiungi Sorgente Risoluzione ID Chiamante Aggiungi Sorgente Sorgente Risoluzione ID Sorgente Risoluzione ID Chiamante Salva risultati: Risoluzione ID Chiamante (CID) Sorgenti Risoluzione CID Controllo Nome Database Database: Decide se salvare o no i dati in astDB; questo comporterà la sovrascrittura di eventuali dati già presenti. Questo non influisce sul comportamento della sorgente Interna. Elimina sorgente Risoluzione ID Chiamante ERRORE: fallito: Modifica Sorgente Immettere una descrizione per questa sorgente. FATALE: fallita trasformazione vecchie rotte: HTTP Nome host o Indirizzo IP Host: Interno Migrazione rotta canali verso rotta Selezione Passante Zap.. MySQL Host MySQL Password MySQL Nome utente MySQL Nessuna Non Necessario Non ancora implementato OK La password utilizzata nell'autenticazione HTTP Password: Il percorso del file GET<br/>e.s.: /cidlookup.php Percorso: Porta HTTP di ascolto (predefinita 80) Porta: Query, il campo speciale '[NUMBER]' sarà sostituito dal numero di telefono<br/>e.s.: number=[NUMBER]&source=crm Query, il campo speciale '[NUMBER]' sarà sostituito dal numero di telefono<br/>e.s.: SELECT name FROM phonebook WHERE number LIKE '%[NUMBER]%' Query: Rimozione campo canale obsoleto dall'entrata.. Selezionare il tipo di sorgente, si può scegliere tra:<ul><li>Interna: utilizza astdb come sorgente e il modulo rubrica per l'inserimento</li><li>ENUM: utilizza il sistema DNS come sorgente di risoluzione e le zone ENUM come configurate in enum.conf</li><li>HTTP: esegue un GET HTTP passando il numero di telefono come argomento per risolvere il nome</li><li>MySQL esegue una query ad un database MySQL per la risoluzione dei nomi</li></ul> Sorgente Descrizione Sorgente: Tipo sorgente: Sorgente: %s (id %s) Le sorgenti possono essere aggiunte nella sezione Risoluzione Nome Chiamante Conferma Cambiamenti SugarCRM Il Nome utente utilizzato nell'autenticazione HTTP Nome utente: eliminato non presente rimosso 