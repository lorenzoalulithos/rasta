export default defineAppConfig({
    ui: {
        colors: {
            primary: 'ocra',
            neutral: 'cemento',
        },
        // Bottoni e campi grandi: si usano con una mano, anche al sole (docs/flows.md "Principi UI")
        button: {
            defaultVariants: {
                size: 'lg',
            },
            compoundVariants: [
                // Testo nero sull'ocra: il bianco non si legge
                { color: 'primary', variant: 'solid', class: 'text-cemento-900 hover:bg-primary-400 font-bold' },
            ],
        },
        badge: {
            compoundVariants: [
                { color: 'primary', variant: 'solid', class: 'text-cemento-900' },
            ],
        },
        input: {
            defaultVariants: {
                size: 'xl',
            },
        },
    },
})
