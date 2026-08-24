(function () {
  'use strict';

  window.SE_BOOKING_DEMO_CONFIG = {
    locale: 'it-IT',
    currency: 'EUR',
    schemaVersion: 'demo-1.0.0',

    parish: {
      id: 'par_sant_eugenio_demo',
      name: 'Parrocchia Sant\'Eugenio',
      logo: {
        src: 'assets/img/logo-parrocchia.svg',
        alt: 'Logo dimostrativo della Parrocchia Sant\'Eugenio'
      },
      primaryColor: '#1a365d',
      primaryDarkColor: '#10243f'
    },

    activities: [
      {
        id: 'act_pellegrinaggi_demo',
        parishId: 'par_sant_eugenio_demo',
        name: 'Pellegrinaggi e Cammini',
        logo: {
          src: 'assets/img/logo-attivita.svg',
          alt: 'Logo dimostrativo di Pellegrinaggi e Cammini'
        },
        primaryColor: '#337ab7',
        primaryDarkColor: '#285f8f'
      }
    ],

    event: {
      id: 'evt_cammino_2027_demo',
      activityId: 'act_pellegrinaggi_demo',
      slug: 'cammino-portoghese-2027-demo',
      title: 'Cammino di Santiago 2027',
      subtitle: 'Cammino Portoghese da Tui a Santiago',
      startDateLabel: '23 maggio 2027',
      endDateLabel: '30 maggio 2027',
      timeZone: 'Europe/Rome',
      location: 'Tui → Santiago de Compostela, Spagna',
      shortDescription: 'Otto giorni di cammino, fraternità e scoperta lungo gli ultimi chilometri del Cammino Portoghese.',
      availabilityLabel: 'Iscrizioni aperte · 12 posti disponibili',
      preliminaryNotice: 'Porta con te un documento valido per l\'espatrio. Programma, prezzi e disponibilità di questo prototipo sono puramente dimostrativi.',
      heroImage: {
        src: 'assets/img/hero-cammino.svg',
        alt: 'Illustrazione dimostrativa di un sentiero tra colline diretto verso Santiago'
      },
      logoOverride: {
        src: 'assets/img/logo-evento.svg',
        alt: 'Logo dimostrativo del Cammino di Santiago 2027'
      },
      primaryColor: '#67458f',
      primaryDarkColor: '#4b3269',
      minTicketsPerOrder: 1,
      maxTicketsPerOrder: 6,
      depositPerTicketCents: 15000,
      bookingCode: 'CS27-K4M8Q2',
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
