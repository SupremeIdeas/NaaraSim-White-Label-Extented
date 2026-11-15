import{h as f,i as a,f as m}from"./ConstantsUtil-wA8GK4UF.js";import{c as p}from"./index-CraI3ZTD.js";import"./index-cLBnk_YB.js";import"./events-CZUGPm6g.js";import"./_commonjsHelpers-D6-XlEtG.js";import"./formatUnits-Ctax2tuV.js";import"./preload-helper-BfFHrpNk.js";import"./index-kP64QGbw.js";import"./index-Fzu5AnDT.js";import"./index-B2EZ3qoP.js";import"./index-CMhTNUj4.js";const d=f`
  :host > wui-flex:first-child {
    height: 500px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none;
  }

  :host > wui-flex:first-child::-webkit-scrollbar {
    display: none;
  }
`;var u=function(o,i,e,r){var n=arguments.length,t=n<3?i:r===null?r=Object.getOwnPropertyDescriptor(i,e):r,l;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")t=Reflect.decorate(o,i,e,r);else for(var s=o.length-1;s>=0;s--)(l=o[s])&&(t=(n<3?l(t):n>3?l(i,e,t):l(i,e))||t);return n>3&&t&&Object.defineProperty(i,e,t),t};let c=class extends a{render(){return m`
      <wui-flex flexDirection="column" .padding=${["0","3","3","3"]} gap="3">
        <w3m-activity-list page="activity"></w3m-activity-list>
      </wui-flex>
    `}};c.styles=d;c=u([p("w3m-transactions-view")],c);export{c as W3mTransactionsView};
