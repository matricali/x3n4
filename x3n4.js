Object.defineProperty(Number.prototype, 'fileSize', {
    value: function () {
        var exp = Math.log(this) / Math.log(1024) | 0
        return (this / Math.pow(1024, exp)).toFixed(2) + ' ' + (exp == 0 ? 'bytes': 'KMGTPEZY'[exp - 1] + 'B')
    },
    writable: false,
    enumerable: false
})

function X3N4 () {
  this.version = window.x3n4_version || 'Custom version'
  this.script_path = window.script_path || '/x3n4.php'
  this.directory_separator = window.directory_separator || '/'

  this.safeTags = function (str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  }

  this.formatDate = function (d) {
    var date = new Date(d)
    return (date.getMonth() + 1) + '/' + date.getDate() + '/' + date.getFullYear()
  }

  this.loadingStart = function () {
    $('body').addClass('loading')
  }

  this.loadingStop = function () {
    $('body').removeClass('loading')
  }

  this.execCommand = function (command) {
    if (command.trim() === 'clear') {
      $('#stdout').html('')
      return
    }

    this.loadingStart()
    $.post(this.script_path, {cmd: command}, function (data) {
      window.x3n4.loadingStop()
      if (data.stdout) {
        $('#stdout').append(window.x3n4.safeTags(data.banner + ' ' + command + '\n'))
        if (data.stdout !== null) {
          $('#stdout').append(window.x3n4.safeTags(data.stdout))
        }
        $('#pwd').html(window.x3n4.safeTags(data.banner))
      } else {
        $('#stdout').append(window.x3n4.safeTags(data))
      }
      $('#stdout').scrollTop($('#stdout')[0].scrollHeight)
    })
  }

  this.evalPhp = function (code) {
    var evalt1 = Date.now()
    this.loadingStart()
    $.post(this.script_path, {eval: code}, function (data) {
      var evaltime = Date.now() - evalt1
      window.x3n4.loadingStop()
      console.log(data)
      if (data.stdout) {
        $('#php-stdout').html(data.stdout)
      } else {
        $('#php-stdout').html(data)
      }
      if (data.took !== undefined) {
        $('#eval-time-took').html('<code>Request time: ' + evaltime + 'ms.</code> <code>PHP process time: ' + data.took + 'ms.</code>')
      } else {
        $('#eval-time-took').html('<code>Request time: ' + evaltime + 'ms.</code>')
      }
    })
  }

  this.clickExecCommand = function () {
    window.x3n4.execCommand($('#stdin').val())
    $('#stdin').val('')
  }

  this.clickEval = function () {
    var code = ''
    if (window.editorPhp) {
      code = window.editorPhp.getValue()
    } else {
      code = $('#php-code').val()
    }
    if (code !== undefined) {
      window.x3n4.evalPhp(code)
    }
  }

  this.checkUpdate = function () {
    $.get('https://api.github.com/repos/jorge-matricali/x3n4/releases', function (data) {
      if (window.x3n4.version !== data[0].tag_name) {
        $('#stdout').append('/!\\ x3n4 ' + data[0].tag_name + " available. Type 'upgrade' to download the latest version automatically.\n")
        $('#stdout').scrollTop($('#stdout')[0].scrollHeight)
      }
    })
  }

  this.declareCallbacks = function () {
    $('#btnExecCommand').on('click', this.clickExecCommand)
    $('#btnEval').on('click', this.clickEval)
    $('#stdin').on('keypress', function (ev) {
      if ((ev.keyCode ? ev.keyCode : ev.which) === 13) {
        $('#btnExecCommand').click()
      }
    })
    $('a[href="#console"]').on('click', function (ev) {
      window.setTimeout(function () {
        $('#stdin').focus()
      }, 200)
    })

    this.fileManagerGetDirectory('.')
  }

  this.fileManagerPathLinks = function (path) {
    var html = ''
    var cpath = ''
    $.each(path.split(window.x3n4.directory_separator), function (i, el) {
      if (el === '') return
      cpath += window.x3n4.directory_separator + el
      html += window.x3n4.directory_separator + '<a href="#file-manager" onclick="window.x3n4.fileManagerGetDirectory(\'' + cpath + '\')" title="' + cpath + '">' + el + '</a>'
    })
    return html
  }

  this.fileManagerGetDirectory = function (path) {
    this.loadingStart()
    $.get(this.script_path + '?dir=' + path, function (data) {
      window.x3n4.loadingStop()
      window.x3n4.fileManagerRenderDirectory(data)
    })
  }

  this.fileManagerGetFile = function (path) {
    $('#file-manager').html('<p><i class="fa fa-chevron-right"></i> ' + window.x3n4.fileManagerPathLinks(path) + '</p>')
    this.loadingStart()
    $.get(this.script_path + '?file=' + path, function (data) {
      window.x3n4.loadingStop()
      $('#file-manager').append($('<pre>' + window.x3n4.safeTags(data.content) + '</pre>'))
    })
  }

  this.fileManagerRenderDirectory = function (data) {
    var html = '<p class="pull-right">'
    if (data.isWritable) {
      html += '<button class="btn btn-default" title="Create new file"><small><i class="fa fa-plus"></i></small> <i class="fa fa-file-o"></i></button> '
      html += '<button class="btn btn-default" title="Create new folder"><small><i class="fa fa-plus"></i></small> <i class="fa fa-folder-open-o"></i></button> '
    }
    html += '</p>'
    html += '<p><i class="fa fa-chevron-right"></i> ' + window.x3n4.fileManagerPathLinks(data.path) + '</p>'
    html += '<div class="clearfix"></div>'
    html += '<div class="table-responsive">'
    html += '<table class="table"><thead><tr><th><input type="checkbox" /></span></th><th>Name</th><th>Permissions</th><th>Size</th><th>Modified date</th><th>Actions</th></tr></thead><tbody>'
    if (data.files) {
      $.each(data.files, function (i, el) {
        if (el.filename !== '.' && el.filename !== '..') {
          html += '<tr><td><input type="checkbox" /></td><td>'
          if (el.type === 'folder') {
            html += '<i class="fa fa-folder"></i> '
            html += '<a href="#file-manager" onclick="window.x3n4.fileManagerGetDirectory(\'' + el.fullpath + '\')" title="' + el.filename + '">' + el.filename + '</a>'
          } else {
            html += '<i class="fa fa-file-o"></i> '
            html += '<a href="#file-manager" onclick="window.x3n4.fileManagerGetFile(\'' + el.fullpath + '\')" title="' + el.filename + '">' + el.filename + '</a>'
          }

          html += '</td><td>' + (el.permissions ? el.permissions : '') + '</td>'
          html += '<td>' + (el.type !== 'folder' ? '<span data-toggle="tooltip" title="' + el.size + ' bytes">' + el.size.fileSize() : '') + '</span></td>'
          html += '<td>' + (el.modifiedAt && el.modifiedAt !== '' ? window.x3n4.formatDate(el.modifiedAt) : '') + '</td>'
          html += '<td><button type="button" class="btn btn-default pull-right"><i class="fa fa-list"></i></button></td>'
          // ---
          html += '</tr>'
        }
      })
    }
    html += '</tbody></table></div>'
    $('#file-manager').html(html)
  }
}
window.x3n4 = new X3N4()
window.x3n4.declareCallbacks()
window.x3n4.checkUpdate()
