let Beamsettings = window.wc.wcSettings.getSetting('woocommerce-gateway-beamcheckout_data', {});
let Beamlabel = window.wp.htmlEntities.decodeEntities(Beamsettings.title) || window.wp.i18n.__('Beam Checkout', 'beam_gateway');
let icon = Beamsettings.icon;
let BeamContent = () => {
    return window.wp.htmlEntities.decodeEntities(Beamsettings.description || '');
};

let BeamlabelwithIcon = window.wp.element.createElement(
    "span",
    { style: { display: 'flex', justifyContent: 'space-between', width: '100%' } },
    Beamlabel + ' ',
    window.wp.element.createElement("img", { src: icon, alt: Beamlabel, style: { float: 'right', maxHeight: '20px' } })
);

let Beam_Block_Gateway = {
    name: 'woocommerce-gateway-beamcheckout',
    label: BeamlabelwithIcon,
    content: Object(window.wp.element.createElement)(BeamContent, null),
    edit: Object(window.wp.element.createElement)(BeamContent, null),
    canMakePayment: () => true,
    ariaLabel: Beamlabel,
    supports: {
        features: Beamsettings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Beam_Block_Gateway);