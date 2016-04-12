jQuery(document).ready(function($){
  var mepr_populate_states = function(obj) {
    var country = obj.val();
    var form = obj.closest('.mepr-form');

    if(form == undefined) { return; }

    // Clean out all the options before re-populating
    form.find('.mepr-states-dropdown option').remove();

    var states_dropdown = form.find('.mepr-states-dropdown');
    var states_text = form.find('.mepr-states-text');

    // Ensure we've found a dropdown & text element
    if (states_dropdown.length <= 0 || states_text.length <= 0) { return; }

    // Grab fieldname and value
    var fieldname = states_dropdown.data('fieldname');
    var value = states_dropdown.data('value').toString();

    if(fieldname == undefined || value == undefined) { return; }

    // Clean up trailing whitespace
    fieldname = fieldname.replace(/^\s+/,'');
    fieldname = fieldname.replace(/\s+$/,'');
    value = value.replace(/^\s+/,'');
    value = value.replace(/\s+$/,'');

    var required = !!obj.attr('required');

    if (MeprI18n.states[country] !== undefined) {
      states_dropdown.attr('name', fieldname);
      states_dropdown.show();
      states_text.removeAttr('name');
      states_text.hide();
      if (required) {
        states_text.removeAttr('required');
        states_dropdown.attr('required','');
      }

      for (var st in MeprI18n.states[country]) {
        var selected = (value===st ? ' selected="selected"' : '');
        states_dropdown.append('<option value="' + st + '" ' + selected + '>' + MeprI18n.states[country][st] + '</option>');
      }
    }
    else {
      states_dropdown.removeAttr('name');
      states_dropdown.hide();
      states_text.attr('name', fieldname);
      states_text.show();
      if (required) {
        states_dropdown.removeAttr('required');
        states_text.attr('required','');
      }
    }
  };

  var mepr_set_locate_inputs = function(country, state) {
    if(country !== '' && state !== '') {
      // If the states are created for this country
      // but the state is goofy then just blank it out
      if(MeprI18n.states[country]!==undefined &&
         MeprI18n.states[country][state]===undefined) {
        state = '';
      }
    }

    // Set the correct values for the country or state dropdowns
    $.each($('.mepr-form .mepr-countries-dropdown'), function(i, obj) {
      if($(obj).val()===undefined || $(obj).val()==='') {
        $(obj).val(country);
      }
    });

    // Populate States properly
    $.each($('.mepr-form .mepr-countries-dropdown'), function(i, obj) {
      mepr_populate_states($(obj));
    });

    // Set the states up properly where appropriate
    $.each($('.mepr-form .mepr-states-dropdown'), function(i, obj) {

      if($(obj).data('value')===undefined || $(obj).data('value')==='') {
        var options = $(obj).find('option');

        $.each(options, function(i, option) {
          if(state===option.value) {
            $(obj).attr('data-value',state);
            $(option).attr('selected','selected');
            return false;
          }
        });
      }

    });

    if ($('.mepr-form .mepr-geo-country').length > 0) {
      $.each($('.mepr-form .mepr-geo-country'), function(i, obj) {
        $(obj).val(country);
      });
    }

    $('.mepr-form .mepr-countries-dropdown').trigger('mepr-geolocated');
  };

  var mepr_ssl_geoip_services = {
    // The services
    caseproof: {
      url:    'https://cspf-locate.herokuapp.com?callback=?',
      cindex: 'country_code',
      sindex: 'region_code',
      used:   false,
      type:   'jsonp',
    },
    telize: {
      url:    'https://www.telize.com/geoip',
      cindex: 'country_code',
      sindex: 'region_code',
      used: false,
      type: 'json',
    },
    //geoplugin: {
    //  url:    'http://www.geoplugin.net/json.gp?jsoncallback=?',
    //  cindex: 'geoplugin_countryCode',
    //  sindex: 'geoplugin_regionCode',
    //  used: false,
    //  type: 'jsonp',
    //},
    //'ip-api': {
    //  url:    'http://ip-api.com/json/?callback=?',
    //  cindex: 'countryCode',
    //  sindex: 'region',
    //  used: false,
    //  type: 'jsonp',
    //},
    freegeoip: {
      url:    'https://freegeoip.net/json/',
      cindex: 'country_code',
      sindex: 'region_code',
      used: false,
      type: 'json',
    }
  };

  var mepr_locate = function(source_key) {
    if(source_key==undefined) {
      source_key = 'caseproof'; // default
    }

    source = mepr_ssl_geoip_services[source_key];

    // If we've already used this source then assume we're out of choices
    if(source.used===true) {
      return mepr_set_locate_inputs('','');
    }

    $.ajax({
      url: source.url,
      method: 'GET',
      timeout: 5000, // 5 seconds ... too much? too little?
      dataType: source.type,
    })
    .done (function(data) {
      var state   = ((data[source.sindex]!==undefined) ? data[source.sindex] : '');
      var country = ((data[source.cindex]!==undefined) ? data[source.cindex] : '');

      mepr_set_locate_inputs(country, state);
    })
    .fail (function() {
      mepr_ssl_geoip_services[source_key].used=true;

      for (var k in mepr_ssl_geoip_services) {
        var next_source = mepr_ssl_geoip_services[k];

        if(next_source.used===false) {
          return mepr_locate(k);
        }
      }

      mepr_set_locate_inputs('','');
    });

    return false;
  };

  if (($('.mepr-form .mepr-countries-dropdown').length > 0) && ($('.mepr-form .mepr-states-dropdown').length > 0)) {
    var located = false;

    $.each($('.mepr-form .mepr-countries-dropdown'), function(i, obj) {
      if ($(obj).val()==='' || $(obj).val()!==undefined) {
        if (!located) {
          mepr_locate();
          located=true;
        }
      }
      else {
        // only locate if countries dropdown isn't set
        located=true;
      }

      mepr_populate_states($(obj));

      $(obj).on('change', function(e) {
        mepr_populate_states($(obj));
      });
    });
  }
});

