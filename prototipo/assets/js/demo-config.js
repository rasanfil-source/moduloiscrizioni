(function () {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const flow = ['registration', 'priced', 'deposit'].includes(params.get('flow'))
    ? params.get('flow')
    : 'deposit';
  const fields = ['minimal', 'extended'].includes(params.get('fields'))
    ? params.get('fields')
    : 'minimal';

  window.SE_BOOKING_DEMO_CONFIG = {
    locale: 'it-IT',
    currency: 'EUR',
    schemaVersion: 'demo-1.0.0',

    parish: {
      id: 'par_demo',
      name: 'Parrocchia Demo',
      logo: {
        src: 'assets/img/logo-parrocchia.svg',
        alt: 'Logo dimostrativo della Parrocchia Demo'
      },
      primaryColor: '#1a365d',
      primaryDarkColor: '#10243f'
    },

    activities: [
      {
        id: 'act_pellegrinaggi_demo',
        parishId: 'par_demo',
        name: 'Attività Cammini Demo',
        logo: {
          src: 'assets/img/logo-attivita.svg',
          alt: 'Logo dimostrativo di Attività Cammini Demo'
        },
        primaryColor: '#337ab7',
        primaryDarkColor: '#285f8f'
      }
    ],

    event: {
      id: 'evt_percorso_2030_demo',
      activityId: 'act_pellegrinaggi_demo',
      slug: 'percorso-collinare-2030-demo',
      title: 'Percorso collinare dimostrativo 2030',
      subtitle: 'Evento fittizio per provare iscrizioni e pagamenti',
      startDateLabel: '23 maggio 2030',
      endDateLabel: '30 maggio 2030',
      timeZone: 'Europe/Rome',
      location: 'Località Demo, Italia',
      shortDescription: 'Otto giorni dimostrativi di cammino e attività di gruppo in una località completamente fittizia.',
      availabilityLabel: 'Iscrizioni aperte · 12 posti disponibili',
      preliminaryNotice: 'Porta con te un documento valido per l\'espatrio. Programma, prezzi e disponibilità di questo prototipo sono puramente dimostrativi.',
      heroImage: {
        src: 'assets/img/hero-cammino.svg',
        alt: 'Illustrazione dimostrativa di un sentiero tra colline'
      },
      logoOverride: {
        src: 'assets/img/logo-evento.svg',
        alt: 'Logo dimostrativo dell’evento Percorso 2030'
      },
      primaryColor: '#67458f',
      primaryDarkColor: '#4b3269',
      minTicketsPerOrder: 1,
      maxTicketsPerOrder: 6,
      depositPerTicketCents: 15000,
      bookingCode: 'EV30-K4M8Q2',
      registrationStatus: 'Registrata',
      paymentStatus: 'In attesa di verifica',
      tickets: [
        {
          id: 'quota-doppia',
          name: 'Quota in camera doppia',
          description: 'Pernottamenti e servizi base inclusi.',
          priceCents: 64000,
          maxPerOrder: 6,
          availabilityLabel: '10 disponibili',
          initialQuantity: 1
        },
        {
          id: 'quota-singola',
          name: 'Quota in camera singola',
          description: 'Disponibilità limitata, soggetta a conferma.',
          priceCents: 79000,
          maxPerOrder: 2,
          availabilityLabel: '2 disponibili',
          initialQuantity: 0
        }
      ],
      orderOptions: [
        {
          id: 'contributo-fondo',
          name: 'Contributo al fondo pellegrini',
          description: 'Contributo facoltativo riferito all\'intero ordine.',
          priceCents: 2000
        }
      ],
      ticketOptions: [
        {
          id: 'colazioni',
          name: 'Pacchetto colazioni',
          description: 'Opzione per il singolo biglietto.',
          priceCents: 7000
        },
        {
          id: 'assicurazione',
          name: 'Assicurazione annullamento',
          description: 'Opzione per il singolo biglietto.',
          priceCents: 2000
        },
        {
          id: 'transfer',
          name: 'Transfer aeroporto A/R',
          description: 'Opzione per il singolo biglietto.',
          priceCents: 2500
        }
      ],
      consentVersion: 'privacy-demo-v1'
    },

    demoScenario: {
      flow,
      fields,
      pricingMode: flow === 'registration' ? 'NONE' : 'CALCULATED',
      collectionMode: flow === 'priced' ? 'NOT_MANAGED' : (flow === 'registration' ? 'NONE' : 'TRACKED_MANUAL'),
      paymentPlan: flow === 'deposit' ? 'DEPOSIT_BALANCE' : 'NONE'
    },

    demoBrandModes: {
      activity: {
        label: 'Logo attività (ereditato)',
        description: 'L\'evento non applica un override e usa il logo dell\'attività.'
      },
      event: {
        label: 'Logo evento (override)',
        description: 'Il logo specifico dell\'evento prevale su quello dell\'attività.'
      },
      parish: {
        label: 'Logo parrocchia (fallback)',
        description: 'Simula l\'assenza dei loghi di evento e attività.'
      }
    }
  };
}());
